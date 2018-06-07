<?php
/**
 * Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\OracleDriver\Database\Driver;

use CakeDC\OracleDriver\Database\OCI8\OCI8Connection;
use CakeDC\OracleDriver\Database\Statement\Method\MethodOracleStatement;
use CakeDC\OracleDriver\Database\Statement\Method\MethodPDOStatement;
use Cake\Database\Driver;
use PDO;

class OracleOCI extends OracleBase
{

    /**
     * @inheritDoc
     */
    protected function _connect($dsn, array $config)
    {
        $connection = new OCI8Connection($dsn, $config['username'], $config['password'], $config['flags']);
        $this->setConnection($connection);
        return true;

    }

    /**
     * @inheritDoc
     */
    public function enabled()
    {
        return function_exists('oci_connect');
    }

    /**
     * @inheritDoc
     */
    public function isConnected()
    {
        if ($this->_connection === null) {
            $connected = false;
        } else {
            try {
                $connected = $this->_connection->query('SELECT 1 FROM DUAL');
            } catch (\PDOException $e) {
                $connected = false;
            }
        }
        $this->connected = !empty($connected);
        return $this->connected;
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId($table = null, $column = null)
    {
        $sequenceName = 'seq_' . strtolower($table);
        $this->connect();
        $statement = $this->_connection->query("SELECT {$sequenceName}.CURRVAL FROM DUAL");
        $result = $statement->fetch(PDO::FETCH_NUM);
        return $result[0];
    }

    /**
     * Prepares a PL/SQL statement to be executed.
     *
     * @param string $queryString The PL/SQL to convert into a prepared statement.
     * @param array $options Statement options.
     * @return \Cake\Database\StatementInterface
     */
    public function prepareMethod($queryString, $options = [])
    {
        $this->connect();
        $innerStatement = $this->_connection->prepare($queryString);
        $statement = new MethodPDOStatement($innerStatement, $this);
        if (!empty($options['bufferResult'])) {
            $statement = new MethodOracleStatement($statement, $this);
        }
        $statement->queryString = $queryString;

        return $statement;
    }

    /**
     * Should if driver support OCI layer.
     *
     * @return bool
     */
    public function isOci() {
        return true;
    }

}
