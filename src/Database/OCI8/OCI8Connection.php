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

use Cake\Core\InstanceConfigTrait;
use PDO;
use UnexpectedValueException;

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
    protected bool $_inTransaction = false;

    /**
     * Database connection.
     *
     * @var resource
     */
    protected $dbh;

    /**
     * @var int
     */
    protected int $executeMode = OCI_COMMIT_ON_SUCCESS;

    protected $_defaultConfig = [];

    /**
     * Creates a Connection to an Oracle Database using oci8 extension.
     *
     * @param string $dsn Oracle connection string in oci_connect format.
     * @param string $username Oracle username.
     * @param string $password Oracle user's password.
     * @param array $options Additional connection settings.
     * @throws \CakeDC\OracleDriver\Database\OCI8\OCI8Exception
     */
    public function __construct(string $dsn, string $username, string $password, array $options)
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

//            $this->dbh = @oci_connect($username, $password, $dsn, $charset, $sessionMode);
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
    public function getServerVersion(): string
    {
        $versionData = oci_server_version($this->dbh);
        if (!preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', $versionData, $version)) {
            throw new UnexpectedValueException(__('Unexpected database version string "{0}" that not parsed.', $versionData));
        }

        return $version[1];
    }

    /**
     * @inheritDoc
     */
    public function prepare(string $query, array $options = []): OCI8Statement|false
    {
        return new OCI8Statement($this->dbh, $query, $this);
    }

    /**
     * @inheritDoc
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): OCI8Statement|false
    {
        $statement = $this->prepare($query);
        if ($statement === false) {
            return false;
        }

        $statement->execute();

        if ($fetchMode !== null) {
            $statement->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        return $statement;
    }

    /**
     * @inheritDoc
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        $string = str_replace("'", "''", $string);

        return "'" . addcslashes($string, "\000\n\r\\\032") . "'";
    }

    /**
     * @inheritDoc
     */
    public function exec(string $statement): int|false
    {
        $stmt = $this->prepare($statement);
        if ($stmt === false) {
            return false;
        }

        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Returns the current execution mode.
     *
     * @return int
     */
    public function getExecuteMode(): int
    {
        return $this->executeMode;
    }

    /**
     * Returns true if the current process is in a transaction
     *
     * @deprecated Use inTransaction() instead
     * @return bool
     */
    public function isTransaction(): bool
    {
        return $this->inTransaction();
    }

    /**
     * @inheritDoc
     */
    public function inTransaction(): bool
    {
        return $this->executeMode === OCI_NO_AUTO_COMMIT;
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): bool
    {
        $this->executeMode = OCI_NO_AUTO_COMMIT;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function commit(): bool
    {
        if (!oci_commit($this->dbh)) {
            $error = oci_error($this->dbh) ?: ['message' => 'Commit failed', 'code' => 0];
            throw OCI8Exception::fromErrorInfo($error);
        }

        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function rollBack(): bool
    {
        if (!oci_rollback($this->dbh)) {
            $error = oci_error($this->dbh) ?: ['message' => 'Rollback failed', 'code' => 0];
            throw OCI8Exception::fromErrorInfo($error);
        }

        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function errorCode(): ?string
    {
        $error = oci_error($this->dbh);
        if ($error !== false) {
            $error = $error['code'];
        } else {
            return '00000';
        }

        return (string)$error;
    }

    /**
     * @inheritDoc
     */
    public function errorInfo(): array
    {
        $error = oci_error($this->dbh);
        if ($error === false) {
            return ['00000', null, null];
        }

        return [
            (string)($error['code'] ?? '00000'),
            $error['code'] ?? null,
            $error['message'] ?? null,
        ];
    }
}
