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
namespace CakeDC\OracleDriver\Database\OCI8;

use PDO;
use Cake\Core\InstanceConfigTrait;
use PDOStatement;

/**
 * OCI8 implementation of the Connection interface.
 */
class OCI8Connection extends PDO
{
    use InstanceConfigTrait;

    /**
     * Whether currently in a transaction
     *
     * @var bool
     */
    protected $_inTransaction = false;

    /**
     * Database connection.
     *
     * @var resource
     */
    protected $dbh;

    /**
     * @var int
     */
    protected $executeMode = OCI_COMMIT_ON_SUCCESS;

    protected $_defaultConfig = [];

    /**
     * Creates a Connection to an Oracle Database using oci8 extension.
     *
     * @param string $dsn Oracle connection string in oci_connect format.
     * @param string $username Oracle username.
     * @param string $password Oracle user's password.
     * @param array $options Additional connection settings.
     *
     * @throws \CakeDC\OracleDriver\Database\OCI8\OCI8Exception
     */
    public function __construct($dsn, $username, $password, $options)
    {
        $persistent = !empty($options['persistent']);
        $charset = !empty($options['charset']) ? $options['charset'] : null;
        $sessionMode = !empty($options['sessionMode']) ? $options['sessionMode'] : null;

        if ($persistent) {
            if ($charset !== null) {
                if ($sessionMode !== null) {
                    $this->dbh = @oci_pconnect($username, $password, $dsn, $charset, $sessionMode);
                } else {
                    $this->dbh = @oci_pconnect($username, $password, $dsn, $charset);
                }
            } else {
                $this->dbh = @oci_pconnect($username, $password, $dsn);
            }
        } else {
            if ($charset !== null) {
                if ($sessionMode !== null) {
                    $this->dbh = @oci_connect($username, $password, $dsn, $charset, $sessionMode);
                } else {
                    $this->dbh = @oci_connect($username, $password, $dsn, $charset);
                }
            } else {
                $this->dbh = @oci_connect($username, $password, $dsn);
            }
        }

        if (!$this->dbh) {
            throw OCI8Exception::fromErrorInfo(oci_error());
        }

        $this->setConfig($options);
    }

    /**
     * Returns database connection.
     *
     * @return resource
     */
    public function dbh()
    {
        return $this->dbh;
    }

    /**
     * Returns oracle version.
     *
     * @throws \UnexpectedValueException if the version string returned by the database server does not parsed
     * @return int Version number
     */
    public function getServerVersion()
    {
        $versionData = oci_server_version($this->dbh);
        if (!preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', $versionData, $version)) {
            throw new \UnexpectedValueException(__('Unexpected database version string "{0}" that not parsed.', $versionData));
        }

        return $version[1];
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($statement, $options = null): PDOStatement|false
    {
        return new OCI8Statement($this->dbh, $statement, $this);
    }

    /**
     * {@inheritdoc}
     */
    //#[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $args = func_get_args();
        $sql = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($string, $type = \PDO::PARAM_STR): string|false
    {
        if (is_int($string) || is_float($string)) {
            return $string;
        }
        $string = str_replace("'", "''", $string);

        return "'" . addcslashes($string, "\000\n\r\\\032") . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement): int|false
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Returns the current execution mode.
     *
     * @return int
     */
    public function getExecuteMode()
    {
        return $this->executeMode;
    }

    /**
     * Returns true if the current process is in a transaction
     *
     * @deprecated Use inTransaction() instead
     * @return bool
     */
    public function isTransaction()
    {
        return $this->inTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool
    {
        return $this->executeMode === OCI_NO_AUTO_COMMIT;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): bool
    {
        $this->executeMode = OCI_NO_AUTO_COMMIT;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        if (!oci_commit($this->dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }
        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack(): bool
    {
        if (!oci_rollback($this->dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }
        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode(): ?string
    {
        $error = oci_error($this->dbh);
        if ($error !== false) {
            $error = $error['code'];
        } else {
            return '00000';
        }

        return $error;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo(): array
    {
        return oci_error($this->dbh);
    }
}
