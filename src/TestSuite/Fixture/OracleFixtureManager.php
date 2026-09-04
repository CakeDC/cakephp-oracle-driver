<?php
declare(strict_types=1);

/**
 * Copyright 2015 - 2020, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2020, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\OracleDriver\TestSuite\Fixture;

use Cake\Core\Configure;
use Cake\Core\Exception\CakeException;
use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Inflector;
use CakeDC\OracleDriver\Database\Driver\OracleBase;
use CakeDC\OracleDriver\Database\OracleConnection;
use PDOException;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * A factory class to manage the life cycle of test fixtures
 */
class OracleFixtureManager
{
    /**
     * The shared fixture manager instance used across the test run.
     *
     * @var self|null
     */
    protected static ?OracleFixtureManager $_instance = null;

    /**
     * Returns the shared fixture manager instance.
     *
     * @return self
     */
    public static function instance(): OracleFixtureManager
    {
        if (!self::$_instance instanceof OracleFixtureManager) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Was this instance already initialized?
     *
     * @var bool
     */
    protected bool $_initialized = false;

    /**
     * Holds the fixture classes that where instantiated
     *
     * @var array
     */
    protected array $_loaded = [];

    /**
     * Holds the fixture classes that where instantiated indexed by class name
     *
     * @var array
     */
    protected array $_fixtureMap = [];

    /**
     * A map of connection names and the fixture currently in it.
     *
     * @var array
     */
    protected array $_insertionMap = [];

    /**
     * List of TestCase class name that have been processed
     *
     * @var array
     */
    protected array $_processed = [];

    /**
     * Is the test runner being run with `--debug` enabled.
     * When true, fixture SQL will also be logged.
     *
     * @var bool
     */
    protected bool $_debug = false;

    /**
     * Modify the debug mode.
     *
     * @param bool $debug Whether or not fixture debug mode is enabled.
     * @return void
     */
    public function setDebug(bool $debug): void
    {
        $this->_debug = $debug;
    }

    /**
     * Inspects the test to look for unloaded fixtures and loads them
     *
     * @param \Cake\TestSuite\TestCase $test The test case to inspect.
     * @return void
     */
    public function fixturize(TestCase $test): void
    {
        $this->_initDb();
        if (empty($test->codeFixtures) || !empty($this->_processed[$test::class])) {
            return;
        }

        if (!is_array($test->codeFixtures)) {
            $test->codeFixtures = array_map(trim(...), explode(',', $test->codeFixtures));
        }

        $this->_loadCodeFixtures($test);
        $this->_processed[$test::class] = true;
    }

    /**
     * Get the loaded fixtures.
     *
     * @return array
     */
    public function loaded(): array
    {
        return $this->_loaded;
    }

    /**
     * Add aliases for all non test prefixed connections.
     *
     * This allows models to use the test connections without
     * a pile of configuration work.
     *
     * @return void
     */
    protected function _aliasConnections(): void
    {
        $connections = ConnectionManager::configured();
        ConnectionManager::alias('test', 'default');
        $map = [];
        foreach ($connections as $connection) {
            if ($connection === 'test' || $connection === 'default') {
                continue;
            }

            if (isset($map[$connection])) {
                continue;
            }

            if (str_starts_with($connection, 'test_')) {
                $map[substr($connection, 5)] = $connection;
            } else {
                $map['test_' . $connection] = $connection;
            }
        }

        foreach ($map as $alias => $connection) {
            ConnectionManager::alias($connection, $alias);
        }
    }

    /**
     * Initializes this class with a DataSource object to use as default for all fixtures
     *
     * @return void
     */
    protected function _initDb(): void
    {
        if ($this->_initialized) {
            return;
        }

        $this->_aliasConnections();
        $this->_initialized = true;
    }

    /**
     * Looks for fixture files and instantiates the classes accordingly
     *
     * @param \Cake\TestSuite\TestCase $test The test suite to load fixtures for.
     * @return void
     * @throws \UnexpectedValueException when a referenced fixture does not exist.
     */
    protected function _loadCodeFixtures(TestCase $test): void
    {
        if (empty($test->codeFixtures)) {
            return;
        }

        foreach ($test->codeFixtures as $fixture) {
            if (isset($this->_loaded[$fixture])) {
                continue;
            }

            [$type, $pathName] = explode('.', $fixture, 2);
            $path = explode('/', $pathName);
            $name = array_pop($path);
            $additionalPath = implode('\\', $path);

            if ($type === 'core') {
                $baseNamespace = 'Cake';
            } elseif ($type === 'app') {
                $baseNamespace = Configure::read('App.namespace');
            } elseif ($type === 'plugin') {
                [$plugin, $name] = explode('.', $pathName);
                $path = implode('\\', explode('/', $plugin));
                $baseNamespace = Inflector::camelize(str_replace('\\', '\ ', $path));
                $additionalPath = null;
            } else {
                $baseNamespace = '';
                $name = $fixture;
            }

            $name = Inflector::camelize($name);
            $nameSegments = [
                $baseNamespace,
                'Test\CodeFixture',
                $additionalPath,
                $name . 'CodeFixture',
            ];
            $className = implode('\\', array_filter($nameSegments));

            if (class_exists($className)) {
                $this->_loaded[$fixture] = new $className();
                $this->_fixtureMap[$name] = $this->_loaded[$fixture];
            } else {
                $msg = sprintf(
                    'Referenced fixture class "%s" not found. Fixture "%s" was referenced in test case "%s".',
                    $className,
                    $fixture,
                    $test::class,
                );
                throw new UnexpectedValueException($msg);
            }
        }
    }

    /**
     * Runs the drop and create commands on the fixtures if necessary.
     *
     * @param \CakeDC\OracleDriver\TestSuite\Fixture\MethodTestFixture $fixture the fixture object to create
     * @param \Cake\Database\Connection $db The Connection object instance to use
     * @param array $sources The existing tables in the datasource.
     * @param bool $drop whether drop the fixture if it is already created or not
     * @return void
     */
    protected function _setupMethod(MethodTestFixture $fixture, ConnectionInterface $db, array $sources, bool $drop = true): void
    {
        $configName = $db->configName();
        if ($this->isFixtureSetup($configName, $fixture)) {
            return;
        }

        $name = $fixture->name();
        $exists = in_array($name, $sources);

        if ($exists) {
            $fixture->drop($db);
            $fixture->create($db);
        } elseif (!$exists) {
            $fixture->create($db);
        }

        $this->_insertionMap[$configName][] = $fixture;
    }

    /**
     * Creates the fixtures tables and inserts data on them.
     *
     * @param \Cake\TestSuite\TestCase $test The test to inspect for fixture loading.
     * @return void
     * @throws \Cake\Core\Exception\CakeException When fixture records cannot be inserted.
     */
    public function load(TestCase $test): void
    {
        if (empty($test->codeFixtures)) {
            return;
        }

        $fixtures = $test->codeFixtures;
        if (!$this->_autoFixturesEnabled($test)) {
            return;
        }

        try {
            $createMethods = function (OracleConnection $db, $fixtures) use ($test): void {
                $methods = $db->methodSchemaCollection()->listMethods();
                $configName = $db->configName();
                $this->_insertionMap[$configName] ??= [];

                foreach ($test->codeFixtures as $fixtureKey) {
                    if (empty($this->_loaded[$fixtureKey])) {
                        continue;
                    }

                    $fixture = $this->_loaded[$fixtureKey];
                    if (!$this->isFixtureSetup($configName, $fixture)) {
                        $this->_setupMethod(
                            $fixture,
                            $db,
                            $methods,
                            $this->_dropTablesEnabled($test),
                        );
                    }
                }
            };
            $this->_runOperation($fixtures, $createMethods);
        } catch (PDOException $pdoException) {
            $msg = sprintf('Unable to insert fixtures for "%s" test case. %s', $test::class, $pdoException->getMessage());
            throw new CakeException($msg, $pdoException->getCode(), $pdoException);
        }
    }

    /**
     * Run a function on each connection and collection of fixtures.
     *
     * @param array $fixtures A list of fixtures to operate on.
     * @param callable $operation The operation to run on each connection + fixture set.
     * @return void
     */
    protected function _runOperation(array $fixtures, callable $operation): void
    {
        $dbs = $this->_fixtureConnections($fixtures);
        foreach ($dbs as $connection => $fixtures) {
            $db = ConnectionManager::get($connection, false);
            assert($db instanceof OracleConnection);
            $driver = $db->getDriver();
            $logQueries = $this->_isQueryLoggingEnabled($driver);

            if ($logQueries && !$this->_debug) {
                $driver->disableQueryLogging();
            }

            $db->transactional(function (Connection $db) use ($fixtures, $operation): void {
                $db->disableConstraints(function (Connection $db) use ($fixtures, $operation): void {
                    $operation($db, $fixtures);
                });
            });
            if ($logQueries) {
                $driver->enableQueryLogging();
            }
        }
    }

    /**
     * @param \Cake\Database\Driver $driver Database driver.
     * @return bool
     */
    protected function _isQueryLoggingEnabled(Driver $driver): bool
    {
        if (!$driver instanceof OracleBase) {
            return false;
        }

        return $driver->isQueryLoggingEnabled();
    }

    /**
     * @param \Cake\TestSuite\TestCase $test Test case instance.
     * @return bool
     */
    protected function _autoFixturesEnabled(TestCase $test): bool
    {
        if (!property_exists($test, 'autoFixtures')) {
            return true;
        }

        $property = new ReflectionProperty($test, 'autoFixtures');

        return (bool)$property->getValue($test);
    }

    /**
     * @param \Cake\TestSuite\TestCase $test Test case instance.
     * @return bool
     */
    protected function _dropTablesEnabled(TestCase $test): bool
    {
        if (!property_exists($test, 'dropTables')) {
            return true;
        }

        $property = new ReflectionProperty($test, 'dropTables');

        return (bool)$property->getValue($test);
    }

    /**
     * Get the unique list of connections that a set of fixtures contains.
     *
     * @param array $fixtures The array of fixtures a list of connections is needed from.
     * @return array An array of connection names.
     */
    protected function _fixtureConnections(array $fixtures): array
    {
        $dbs = [];
        foreach ($fixtures as $f) {
            if (!empty($this->_loaded[$f])) {
                $fixture = $this->_loaded[$f];
                $dbs[$fixture->connection()][$f] = $fixture;
            }
        }

        return $dbs;
    }

    /**
     * Truncates the fixtures tables
     *
     * @param \Cake\TestSuite\TestCase $test The test to inspect for fixture unloading.
     * @return void
     */
    public function unload(TestCase $test): void
    {
    }

    /**
     * Creates a single fixture table and loads data into it.
     *
     * @param string $name of the fixture
     * @param \Cake\Datasource\ConnectionInterface $db Connection instance or leave null to get a Connection from the fixture
     * @param bool $drop Whether or not tables should be dropped and re-created.
     * @return void
     * @throws \UnexpectedValueException if $name is not a previously loaded class
     */
    public function loadSingleMethod(string $name, ?ConnectionInterface $db = null, bool $drop = true): void
    {
        if (isset($this->_fixtureMap[$name])) {
            $fixture = $this->_fixtureMap[$name];
            if (!$db instanceof ConnectionInterface) {
                $db = ConnectionManager::get($fixture->connection());
            }

            assert($db instanceof OracleConnection);
            if (!$this->isFixtureSetup($db->configName(), $fixture)) {
                $methods = $db->methodSchemaCollection()->listMethods();
                $this->_setupMethod($fixture, $db, $methods, $drop);
            }
        } else {
            throw new UnexpectedValueException(sprintf('Referenced fixture class %s not found', $name));
        }
    }

    /**
     * Drop all fixture tables loaded by this class
     *
     * @return void
     */
    public function shutDown(): void
    {
        $shutdown = function ($db, $fixtures): void {
            $connection = $db->configName();
            foreach ($fixtures as $fixture) {
                if ($this->isFixtureSetup($connection, $fixture)) {
                    $fixture->drop($db);
                    $index = array_search($fixture, $this->_insertionMap[$connection], true);
                    unset($this->_insertionMap[$connection][$index]);
                }
            }
        };
        $this->_runOperation(array_keys($this->_loaded), $shutdown);
    }

    /**
     * Check whether or not a fixture has been inserted in a given connection name.
     *
     * @param string $connection The connection name.
     * @param \CakeDC\OracleDriver\TestSuite\Fixture\MethodTestFixture $fixture The fixture to check.
     * @return bool
     */
    public function isFixtureSetup(string $connection, MethodTestFixture $fixture): bool
    {
        return isset($this->_insertionMap[$connection]) && in_array($fixture, $this->_insertionMap[$connection]);
    }
}
