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
namespace CakeDC\OracleDriver\Database;

use Cake\Core\Exception\CakeException;
use Cake\Database\Connection;
use Cake\Database\StatementInterface;
use CakeDC\OracleDriver\Database\Driver\OracleBase;
use CakeDC\OracleDriver\Database\Log\MethodLogger;
use CakeDC\OracleDriver\Database\Log\MethodLoggingStatement;
use CakeDC\OracleDriver\Database\Schema\CachedMethodsCollection;
use CakeDC\OracleDriver\Database\Schema\MethodsCollection;

class OracleConnection extends Connection
{
    /**
     * Driver object, responsible for creating the real connection
     * and provide specific SQL dialect.
     *
     * @var \CakeDC\OracleDriver\Database\Driver\OracleBase
     */
    protected OracleBase $_driver;

    /**
     * Logger object instance.
     *
     * @var \CakeDC\OracleDriver\Database\Log\MethodLogger
     */
    protected ?MethodLogger $_methodLogger = null;

    /**
     * The methods collection object
     *
     * @var \CakeDC\OracleDriver\Database\Schema\MethodsCollection
     */
    protected ?MethodsCollection $_schemaMethodsCollection = null;

    /**
     * Builds oracle connection based on generic cakephp connection class.
     *
     * @param \Cake\Database\Connection $connection Connection object.
     * @return self
     */
    public static function build(Connection $connection): OracleConnection
    {
        $config = $connection->config();
        $config['driver'] = $connection->getDriver();

        return new OracleConnection($config);
    }

    /**
     * Gets or sets a Schema\Collection object for this connection.
     *
     * @param \CakeDC\OracleDriver\Database\Schema\MethodsCollection|null $collection The schema collection object
     * @return \CakeDC\OracleDriver\Database\Schema\MethodsCollection
     */
    public function methodSchemaCollection(?MethodsCollection $collection = null): MethodsCollection
    {
        if ($collection instanceof MethodsCollection) {
            return $this->_schemaMethodsCollection = $collection;
        }

        if ($this->_schemaMethodsCollection instanceof MethodsCollection) {
            return $this->_schemaMethodsCollection;
        }

        if (!empty($this->_config['cacheMetadata'])) {
            return $this->_schemaMethodsCollection = new CachedMethodsCollection($this, $this->_config['cacheMetadata']);
        }

        return $this->_schemaMethodsCollection = new MethodsCollection($this);
    }

    /**
     * Prepares a PL/SQL statement to be executed.
     *
     * @param string $sql The PL/SQL to convert into a prepared statement.
     * @param array $options Method options used on method constructing.
     * @return \CakeDC\OracleDriver\Database\Log\MethodLoggingStatement
     */
    public function prepareMethod(string $sql, array $options = []): MethodLoggingStatement
    {
        $driver = $this->getDriver();
        if (!$driver instanceof OracleBase) {
            throw new CakeException('Method calls require an OracleBase driver');
        }

        if (!$driver->isOci()) {
            throw new CakeException('Method calls using PDO layer not supported');
        }

        $options += ['bufferResult' => false];
        $statement = $driver->prepareMethod($sql, $options);

        return $this->_getMethodLogger($statement);
    }

    /**
     * Returns a new statement object that will log the activity
     * for the passed original statement instance.
     *
     * @param \Cake\Database\StatementInterface $statement the instance to be decorated
     * @return \CakeDC\OracleDriver\Database\Log\MethodLoggingStatement
     */
    protected function _getMethodLogger(StatementInterface $statement): MethodLoggingStatement
    {
        $log = new MethodLoggingStatement($statement, $this->getDriver());
        $log->logger($this->methodLogger());

        return $log;
    }

    /**
     * Sets the method logger object instance. When called with
     * no arguments it returns the currently setup logger instance.
     *
     * @param \CakeDC\OracleDriver\Database\Log\MethodLogger $instance logger object instance
     * @return object logger instance
     */
    public function methodLogger(?MethodLogger $instance = null): object
    {
        if (!$instance instanceof MethodLogger) {
            if (!$this->_methodLogger instanceof MethodLogger) {
                $this->_methodLogger = new MethodLogger();
            }

            return $this->_methodLogger;
        }

        $this->_methodLogger = $instance;

        return $instance;
    }

    /**
     * Returns whether query logging is enabled for this connection's driver.
     *
     * @return bool
     */
    public function isQueryLoggingEnabled(): bool
    {
        $driver = $this->getDriver();
        if (!$driver instanceof OracleBase) {
            return false;
        }

        return $driver->isQueryLoggingEnabled();
    }

    /**
     * @inheritDoc
     */
    public function cacheMetadata(string|bool $cache): void
    {
        $this->_schemaMethodsCollection = null;
        parent::cacheMetadata($cache);
    }
}
