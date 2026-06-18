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
use PDOException;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * A factory class to manage the life cycle of test fixtures
 *
 */
class OracleFixtureManager
{
    /**
     * Was this instance already initialized?
     *
     * @var bool
     */
    protected $_initialized = false;

    /**
     * Holds the fixture classes that where instantiated
     *
     * @var array
     */
    protected $_loaded = [];

    /**
     * Holds the fixture classes that where instantiated indexed by class name
     *
     * @var array
     */
    protected $_fixtureMap = [];

    /**
     * A map of connection names and the fixture currently in it.
     *
     * @var array
     */
    protected $_insertionMap = [];

    /**
     * List of TestCase class name that have been processed
     *
     * @var array
     */
    protected $_processed = [];

    /**
     * Is the test runner being run with `--debug` enabled.
     * When true, fixture SQL will also be logged.
     *
     * @var bool
     */
    protected $_debug = false;

    /**
     * Modify the debug mode.
     *
     * @param bool $debug Whether or not fixture debug mode is enabled.
     * @return void
     */
    public function setDebug($debug)
    {
        $this->_debug = $debug;
    }

    /**
     * Inspects the test to look for unloaded fixtures and loads them
     *
     * @param \Cake\TestSuite\TestCase $test The test case to inspect.
     * @return void
     */
    public function fixturize($test)
    {
        $this->_initDb();
        if (empty($test->codeFixtures) || !empty($this->_processed[get_class($test)])) {
            return;
        }
        if (!is_array($test->codeFixtures)) {
            $test->codeFixtures = array_map('trim', explode(',', $test->codeFixtures));
        }
        $this->_loadCodeFixtures($test);
        $this->_processed[get_class($test)] = true;
    }

    /**
     * Get the loaded fixtures.
     *
     * @return array
     */
    public function loaded()
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
    protected function _aliasConnections()
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
            if (strpos($connection, 'test_') === 0) {
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
    protected function _initDb()
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
    protected function _loadCodeFixtures($test)
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
                    get_class($test)
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
    protected function _setupMethod($fixture, $db, array $sources, $drop = true)
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
     * @throws \Cake\Core\Exception\Exception When fixture records cannot be inserted.
     */
    public function load($test)
    {
        if (empty($test->codeFixtures)) {
            return;
        }

        $fixtures = $test->codeFixtures;
        if (empty($fixtures) || !$this->_autoFixturesEnabled($test)) {
            return;
        }

        try {
            $createMethods = function ($db, $fixtures) use ($test) {
                $methods = $db->methodSchemaCollection()->listMethods();
                $configName = $db->configName();
                if (!isset($this->_insertionMap[$configName])) {
                    $this->_insertionMap[$configName] = [];
                }

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
                            $this->_dropTablesEnabled($test)
                        );
                    }
                }
            };
            $this->_runOperation($fixtures, $createMethods);
        } catch (PDOException $e) {
            $msg = sprintf('Unable to insert fixtures for "%s" test case. %s', get_class($test), $e->getMessage());
            throw new CakeException($msg, $e->getCode(), $e);
        }
    }

    /**
     * Run a function on each connection and collection of fixtures.
     *
     * @param array $fixtures A list of fixtures to operate on.
     * @param callable $operation The operation to run on each connection + fixture set.
     * @return void
     */
    protected function _runOperation($fixtures, $operation)
    {
        $dbs = $this->_fixtureConnections($fixtures);
        foreach ($dbs as $connection => $fixtures) {
            $db = ConnectionManager::get($connection, false);
            assert($db instanceof Connection);
            $driver = $db->getDriver();
            $logQueries = $this->_isQueryLoggingEnabled($driver);

            if ($logQueries && !$this->_debug) {
                $driver->disableQueryLogging();
            }
            $db->transactional(function (Connection $db) use ($fixtures, $operation) {
                $db->disableConstraints(function (Connection $db) use ($fixtures, $operation) {
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
        $property = new ReflectionProperty($driver, 'logQueries');

        return (bool)$property->getValue($driver);
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
    protected function _fixtureConnections($fixtures)
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
    public function unload($test)
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
    public function loadSingleMethod($name, $db = null, $drop = true)
    {
        if (isset($this->_fixtureMap[$name])) {
            $fixture = $this->_fixtureMap[$name];
            if ($db === null) {
                $db = ConnectionManager::get($fixture->connection());
            }

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
    public function shutDown()
    {
        $shutdown = function ($db, $fixtures) {
            $connection = $db->configName();
            foreach ($fixtures as $fixture) {
                if ($this->isFixtureSetup($connection, $fixture)) {
                    $fixture->drop($db);
                    $index = array_search($fixture, $this->_insertionMap[$connection]);
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
    public function isFixtureSetup($connection, $fixture)
    {
        return isset($this->_insertionMap[$connection]) && in_array($fixture, $this->_insertionMap[$connection]);
    }
}
