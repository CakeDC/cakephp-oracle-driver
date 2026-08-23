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
namespace CakeDC\OracleDriver\Database\Schema;

use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\ConnectionInterface;
use PDOException;

/**
 * Represents a database methods collection
 *
 * Used to access information about the methods,
 * and other data in a database.
 */
class MethodsCollection
{
    /**
     * Connection object
     */
    protected \Cake\Datasource\ConnectionInterface $_connection;

    /**
     * Schema dialect instance.
     *
     * @var \Cake\Database\Schema\BaseSchema
     */
    protected $_dialect;

    /**
     * Constructor.
     *
     * @param \Cake\Datasource\ConnectionInterface $connection The connection instance.
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->_connection = $connection;
        $this->_dialect = $connection->getDriver()->schemaDialect();
    }

    /**
     * Get the list of methods available in the current connection.
     *
     * @return array The list of methods in the connected database/schema.
     */
    public function listMethods(): array
    {
        // @todo fix this method to return only high level data
        [$sql, $params] = $this->_dialect->listMethodsSql($this->_connection->config());
        $result = [];
        $statement = $this->_connection->execute($sql, $params);
        while ($row = $statement->fetch()) {
            $result[] = $row[0];
        }
        
        $statement->closeCursor();

        return $result;
    }

    /**
     * Get the list of methods available in the current connection.
     *
     * @param string $name Method name.
     * @return array The list of methods in the connected database/schema.
     */
    public function getMethod($name): array
    {
        $config = $this->_connection->config();
        $config['objectName'] = $name;
        [$sql, $params] = $this->_dialect->listMethodsSql($config);
        $result = [];
        $statement = $this->_connection->execute($sql, $params);
        while ($row = $statement->fetch()) {
            $result[] = $row[0];
        }
        
        $statement->closeCursor();

        return $result;
    }

    /**
     * Get the metadata for a method.
     *
     * Caching will be applied if `cacheMetadata` key is present in the Connection
     * configuration options. Defaults to _cake_method_ when true.
     *
     * ### Options
     *
     * - `forceRefresh` - Set to true to force rebuilding the cached metadata.
     *   Defaults to false.
     *
     * @param string $name The name of the method to describe.
     * @param array $options The options to use, see above.
     * @return \CakeDC\OracleDriver\Database\Schema\MethodSchema Object with method metadata.
     * @throws \Cake\Database\Exception when method cannot be described.
     */
    public function describe(string $name, array $options = []): \CakeDC\OracleDriver\Database\Schema\MethodSchema
    {
        $config = $this->_connection->config();
        $methods = $this->getMethod($name);
        if ($methods === []) {
            throw new DatabaseException(sprintf('Cannot describe %s. Method not found.', $name));
        }
        
        $method = new MethodSchema($name);

        $this->_reflect($method, $name, $config);

        return $method;
    }

    /**
     * Helper method for running each step of the reflection process.
     *
     * @param \CakeDC\OracleDriver\Database\Schema\MethodSchema $method Object with method metadata.
     * @param string $name The method name.
     * @param array $config The config data.
     * @return void
     * @throws \Cake\Database\Exception on query failure.
     */
    protected function _reflect($method, $name, $config)
    {
        [$sql, $params] = $this->_dialect->describeParametersSql($name, $config);
        if (empty($sql)) {
            return;
        }
        
        try {
            $statement = $this->_connection->execute($sql, $params);
        } catch (PDOException $pdoException) {
            throw new DatabaseException($pdoException->getMessage(), 500, $pdoException);
        }
        
        foreach ($statement->fetchAll('assoc') as $row) {
            $this->_dialect->convertParametersDescription($method, $row);
        }
        
        $statement->closeCursor();
    }
}
