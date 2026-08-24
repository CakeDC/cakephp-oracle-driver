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
namespace CakeDC\OracleDriver\Database\Driver;

use Cake\Database\StatementInterface;
use CakeDC\OracleDriver\Database\OCI8\OCI8Connection;
use CakeDC\OracleDriver\Database\Statement\Method\MethodOracleStatement;
use CakeDC\OracleDriver\Database\Statement\Method\MethodPDOStatement;
use PDO;

class OracleOCI extends OracleBase
{
    /**
     * @inheritDoc
     */
    protected function createConnection(string $dsn, array $config): PDO
    {
        $config['flags'] += [
            'charset' => empty($config['encoding']) ? null : $config['encoding'],
            'persistent' => empty($config['persistent']) ? false : $config['persistent'],
        ];

        return new OCI8Connection($dsn, $config['username'], $config['password'], $config['flags']);
    }

    /**
     * @inheritDoc
     */
    public function enabled(): bool
    {
        return function_exists('oci_connect');
    }

    /**
     * @inheritDoc
     */
    public function version(): string
    {
        if ($this->_version === null) {
            $this->connect();
            if ($this->pdo instanceof OCI8Connection) {
                $this->_version = $this->pdo->getServerVersion();
            } else {
                $this->_version = parent::version();
            }
        }

        return $this->_version;
    }

    /**
     * Prepares a PL/SQL statement to be executed.
     *
     * @param string $queryString The PL/SQL to convert into a prepared statement.
     * @param array<string, mixed> $options Statement options.
     * @return \Cake\Database\StatementInterface
     */
    public function prepareMethod(string $queryString, array $options = []): StatementInterface
    {
        $this->connect();
        $innerStatement = $this->getPdo()->prepare($queryString);
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
    public function isOci(): bool
    {
        return true;
    }
}
