<?php
declare(strict_types=1);

$findRoot = function () {
    $root = dirname(__DIR__);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(dirname(__DIR__));
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(dirname(dirname(__DIR__)));
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    return dirname(__DIR__);
};

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

function _define(string $name, mixed $value): void
{
    if (!defined($name)) {
        define($name, $value);
    }
}

_define('ROOT', $findRoot());
_define('APP_DIR', 'App');
_define('WEBROOT_DIR', 'webroot');
_define('APP', ROOT . '/tests/App/');
_define('CONFIG', ROOT . '/tests/Config/');
_define('WWW_ROOT', ROOT . DS . WEBROOT_DIR . DS);
_define('TESTS', ROOT . DS . 'tests' . DS);
_define('TMP', ROOT . DS . 'tmp' . DS);
_define('LOGS', TMP . 'logs' . DS);
_define('CACHE', TMP . 'cache' . DS);
_define('CAKE_CORE_INCLUDE_PATH', ROOT . '/vendor/cakephp/cakephp');
_define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
_define('CAKE', CORE_PATH . 'src' . DS);
_define('CORE_TESTS', CORE_PATH . 'tests' . DS);
_define('CORE_TEST_CASES', CORE_TESTS . 'TestCase');
_define('TEST_APP', CORE_TESTS . 'test_app' . DS);

require_once ROOT . '/vendor/autoload.php';
require_once CORE_PATH . 'config/bootstrap.php';

Cake\Core\Configure::write('App', [
    'namespace' => 'CakeDC\\OracleDriver\\Test\\App',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => 'src',
    'webroot' => WEBROOT_DIR,
    'wwwRoot' => WWW_ROOT,
    'fullBaseUrl' => 'http://localhost',
    'imageBaseUrl' => 'img/',
    'jsBaseUrl' => 'js/',
    'cssBaseUrl' => 'css/',
    'paths' => [
        'plugins' => [dirname(APP) . DS . 'plugins' . DS],
    ],
]);
Cake\Core\Configure::write('debug', true);

foreach (['cache/models', 'cache/persistent', 'cache/views', 'logs'] as $dir) {
    if (!is_dir(TMP . $dir)) {
        mkdir(TMP . $dir, 0777, true);
    }
}

$cache = [
    'default' => [
        'className' => 'File',
        'path' => CACHE,
    ],
    '_cake_translations_' => [
        'className' => 'File',
        'prefix' => 'oracle_driver_cake_translations_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => 'oracle_driver_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_method_' => [
        'className' => 'File',
        'prefix' => 'oracle_driver_cake_method_',
        'path' => CACHE . 'models/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
];

Cake\Cache\Cache::setConfig($cache);
Cake\Core\Configure::write('Session', [
    'defaults' => 'php',
]);

Cake\Chronos\Chronos::setTestNow(Cake\Chronos\Chronos::now());
Cake\Utility\Security::setSalt('oracle-driver-test-salt-value');
Cake\Datasource\FactoryLocator::add('Table', new Cake\ORM\Locator\TableLocator());

if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}

Cake\Datasource\ConnectionManager::setConfig('test', [
    'url' => getenv('db_dsn'),
    'timezone' => 'UTC',
]);

class_alias('CakeDC\OracleDriver\Test\App\Controller\AppController', 'App\Controller\AppController');

$application = new \CakeDC\OracleDriver\Test\App\Application(CONFIG);
$application->bootstrap();
$application->pluginBootstrap();

Cake\Core\Configure::write(
    'TestSuite.fixtureStrategy',
    CakeDC\OracleDriver\TestSuite\Fixture\OracleTruncateStrategy::class,
);

if (getenv('FIXTURE_SCHEMA_METADATA')) {
    $schemaFile = ROOT . '/' . ltrim((string)getenv('FIXTURE_SCHEMA_METADATA'), './');
    $tables = include $schemaFile;
    /** @var \Cake\Database\Connection $connection */
    $connection = Cake\Datasource\ConnectionManager::get('test');
    $connection->getDriver()->enableAutoQuoting(true);
    $driver = $connection->getDriver();
    $recreateSchema = filter_var(
        getenv('ORACLE_RECREATE_SCHEMA') ?: '0',
        FILTER_VALIDATE_BOOLEAN,
    );

    if ($recreateSchema) {
        foreach (array_reverse(array_keys($tables)) as $tableKey) {
            $tableName = $tables[$tableKey]['table'] ?? $tableKey;
            try {
                $connection->execute(sprintf(
                    'DROP TABLE %s CASCADE CONSTRAINTS PURGE',
                    $driver->quoteIdentifier($tableName),
                ));
            } catch (Throwable $dropException) {
                if (!str_contains($dropException->getMessage(), 'ORA-00942')) {
                    throw $dropException;
                }
            }

            try {
                $connection->execute(sprintf(
                    'DROP SEQUENCE %s',
                    $driver->quoteIdentifier('SEQ_' . strtoupper($tableName)),
                ));
            } catch (Throwable $sequenceDropException) {
                if (!str_contains($sequenceDropException->getMessage(), 'ORA-02289')
                    && !str_contains($sequenceDropException->getMessage(), 'ORA-00942')
                ) {
                    throw $sequenceDropException;
                }
            }
        }
    }

    foreach ($tables as $tableName => $table) {
        $name = $table['table'] ?? $tableName;
        $schema = new Cake\Database\Schema\TableSchema($name, $table['columns']);
        if (isset($table['indexes'])) {
            foreach ($table['indexes'] as $key => $index) {
                $schema->addIndex($key, $index);
            }
        }
        if (isset($table['constraints'])) {
            foreach ($table['constraints'] as $key => $constraint) {
                $schema->addConstraint($key, $constraint);
            }
        }
        foreach ($schema->createSql($connection) as $sql) {
            try {
                $connection->execute($sql);
            } catch (Throwable $createException) {
                if (!str_contains($createException->getMessage(), 'ORA-00955')) {
                    throw $createException;
                }
            }
        }
    }
}
