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

use Cake\Database\Driver;
use Cake\Database\DriverFeatureEnum;
use Cake\Database\Exception\QueryException;
use Cake\Database\Query;
use Cake\Database\QueryCompiler;
use Cake\Database\StatementInterface;
use Cake\Http\Exception\NotImplementedException;
use CakeDC\OracleDriver\Database\Dialect\OracleDialectTrait;
use CakeDC\OracleDriver\Database\Oracle12Compiler;
use CakeDC\OracleDriver\Database\OracleCompiler;
use CakeDC\OracleDriver\Database\Statement\OracleStatement;
use PDO;
use PDOException;
use Throwable;

abstract class OracleBase extends Driver
{
    use OracleDialectTrait;

    /**
     * @var int Maximum alias length for Oracle version < 12.2
     */
    protected const MAX_ALIAS_LENGTH = 30;

    /**
     * @var int Maximum alias length for Oracle version >= 12.2
     */
    protected const MAX_ALIAS_LENGTH12 = 128;

    /**
     * @var class-string<\CakeDC\OracleDriver\Database\Statement\OracleStatement>
     */
    protected const STATEMENT_CLASS = OracleStatement::class;

    /**
     * @var array<string, mixed>
     */
    protected array $_baseConfig = [
        'persistent' => true,
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'cake',
        'port' => '1521',
        'flags' => [],
        'encoding' => 'utf8',
        'case' => 'upper',
        'timezone' => null,
        'init' => [],
        'server_version' => 11,
        'autoincrement' => false,
    ];

    protected int|float|string|null $_serverVersion = null;

    protected bool $_autoincrement = false;

    protected string $_startQuote = '"';

    protected string $_endQuote = '"';

    /**
     * @param array<string, mixed> $config Configuration settings.
     */
    public function __construct(array $config = [])
    {
        if (array_key_exists('server_version', $config)) {
            $this->_serverVersion = is_numeric($config['server_version'])
                ? $config['server_version'] + 0
                : $config['server_version'];
        }

        parent::__construct($config);
        $this->_autoincrement = !empty($config['autoincrement']);
    }

    /**
     * @return bool
     */
    public function useAutoincrement(): bool
    {
        return $this->_autoincrement;
    }

    /**
     * Establishes a connection to the database server.
     *
     * @return void
     */
    public function connect(): void
    {
        if ($this->pdo instanceof PDO) {
            return;
        }

        $config = $this->_config;

        $config['init'][] = "ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS' NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS' NLS_TIMESTAMP_TZ_FORMAT='YYYY-MM-DD HH24:MI:SS'";

        $config['flags'] += [
            PDO::NULL_EMPTY_STRING => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => empty($config['persistent']) ? false : $config['persistent'],
            PDO::ATTR_ORACLE_NULLS => true,
        ];

        $dsn = $this->getDSN();
        $this->pdo = $this->createConnection($dsn, $config);

        if (!empty($config['init'])) {
            foreach ((array)$config['init'] as $command) {
                $this->pdo->exec($command);
            }
        }
    }

    /**
     * Create the PDO (or PDO-compatible) connection.
     *
     * @param string $dsn Connection DSN.
     * @param array<string, mixed> $config Connection configuration.
     * @return \PDO
     */
    abstract protected function createConnection(string $dsn, array $config): PDO;

    /**
     * Build DSN string in oracle connection format.
     *
     * @return string
     */
    public function getDSN(): string
    {
        $config = $this->_config;
        if (!empty($config['host'])) {
            if (empty($config['port'])) {
                $config['port'] = 1521;
            }

            $service = 'SERVICE_NAME=' . $config['database'];

            if (!empty($config['sid'])) {
                $serviceName = $config['sid'];
                $service = 'SID=' . $serviceName;
            }

            $pooled = '';
            $instance = '';

            if (isset($config['instance']) && !empty($config['instance'])) {
                $instance = '(INSTANCE_NAME = ' . $config['instance'] . ')';
            }

            if (isset($config['pooled']) && $config['pooled'] == true) {
                $pooled = '(SERVER=POOLED)';
            }

            return '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=' . $config['host'] . ')(PORT=' . $config['port'] . '))' . '(CONNECT_DATA=(' . $service . ')' . $instance . $pooled . '))';
        }

        return $config['database'] ?? '';
    }

    /**
     * Prepares a sql statement to be executed.
     *
     * @param \Cake\Database\Query|string $query The query to convert into a statement.
     * @return \Cake\Database\StatementInterface
     */
    public function prepare(Query|string $query): StatementInterface
    {
        $this->connect();
        $queryStringRaw = $query instanceof Query ? $query->sql() : $query;
        $queryString = $this->_fromDualIfy($queryStringRaw);
        [$queryString, $paramMap] = self::convertPositionalToNamedPlaceholders($queryString);

        try {
            $innerStatement = $this->getPdo()->prepare($queryString);
        } catch (PDOException $pdoException) {
            throw new QueryException($queryString, $pdoException);
        }

        if (!$this->isOci()) {
            try {
                $innerStatement->setAttribute(PDO::ATTR_PREFETCH, 1);
            } catch (PDOException) {
            }
        }

        /** @var \CakeDC\OracleDriver\Database\Statement\OracleStatement $statement */
        $statement = new OracleStatement(
            $innerStatement,
            $this,
            $this->getResultSetDecorators($query),
        );
        $statement->setRawQueryString($queryStringRaw);
        $statement->setParamMap($paramMap);

        return $statement;
    }

    /**
     * Add "FROM DUAL" to SQL statements that are SELECT statements
     * with no FROM clause specified.
     *
     * @param string $queryString query
     * @return string
     */
    protected function _fromDualIfy(string $queryString): string
    {
        $statement = strtolower(trim($queryString));
        if (!str_starts_with($statement, 'select') || preg_match('/\sfrom\s/', $statement)) {
            return $queryString;
        }

        return "{$queryString} FROM DUAL";
    }

    /**
     * Converts positional (?) into named placeholders (:param<num>).
     *
     * @param string $query The SQL statement to convert.
     * @return array{0: string, 1: array<int, string>}
     */
    public function convertPositionalToNamedPlaceholders(string $query): array
    {
        $count = 0;
        $inLiteral = false;
        $stmtLen = strlen($query);
        $paramMap = [];
        for ($i = 0; $i < $stmtLen; $i++) {
            if ($query[$i] === '?' && !$inLiteral) {
                $paramMap[$count] = ":param$count";
                $len = strlen($paramMap[$count]);
                $query = substr_replace($query, ":param$count", $i, 1);
                $i += $len - 1;
                $stmtLen = strlen($query);
                ++$count;
            } elseif ($query[$i] === "'" || $query[$i] === '"') {
                $inLiteral = !$inLiteral;
            }
        }

        return [$query, $paramMap];
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId(?string $table = null, ?string $column = null): string
    {
        if ($this->useAutoincrement()) {
            return (string)$this->_autoincrementSequenceId($table, $column);
        }

        $tableName = $this->normalizeCatalogName($table);
        $sequenceName = 'seq_' . $tableName;
        $this->connect();
        $currval = $this->fetchSequenceCurrval($sequenceName);
        if ($currval !== null) {
            return (string)$currval;
        }

        return (string)$this->_autoincrementSequenceId($table, $column);
    }

    /**
     * Normalizes a table or column name for data-dictionary lookups.
     *
     * @param string|null $name Identifier name.
     * @return string
     */
    protected function normalizeCatalogName(?string $name): string
    {
        $name = trim((string)$name, '"');
        if (str_contains($name, '.')) {
            $parts = explode('.', $name);
            $name = end($parts);
            $name = trim($name, '"');
        }

        if ($this->isAutoQuotingEnabled()) {
            return strtolower($name);
        }

        return strtoupper($name);
    }

    /**
     * Reads CURRVAL for a sequence in the current session.
     *
     * @param string $sequenceName Sequence name.
     * @return string|int|null
     */
    protected function fetchSequenceCurrval(string $sequenceName): int|string|null
    {
        try {
            $statement = $this->getPdo()->query("SELECT {$sequenceName}.CURRVAL FROM DUAL");
            $result = $statement->fetch(PDO::FETCH_NUM);
            if ($result !== false && isset($result[0])) {
                return $result[0];
            }
        } catch (Throwable) {
        }

        return null;
    }

    /**
     * Returns the highest primary-key value as a last-resort insert id.
     *
     * @param string|null $table Table name.
     * @param string|null $column Column name.
     * @return string|int|null
     */
    protected function fetchLastInsertIdFromMax(?string $table, ?string $column): int|string|null
    {
        if ($table === null || $table === '') {
            return null;
        }

        $columnName = $column !== null && $column !== '' ? $column : 'id';
        $quotedTable = $this->quoteIfAutoQuote(trim($table, '"'));
        $quotedColumn = $this->quoteIfAutoQuote($columnName);

        try {
            $statement = $this->getPdo()->query("SELECT MAX({$quotedColumn}) FROM {$quotedTable}");
            $result = $statement->fetch(PDO::FETCH_NUM);
            if ($result !== false && isset($result[0])) {
                return $result[0];
            }
        } catch (Throwable) {
        }

        return null;
    }

    /**
     * Returns last insert id by autoincrement sequence.
     *
     * @param string|null $table Table name.
     * @param string|null $column Column name
     * @return string|int
     */
    protected function _autoincrementSequenceId(?string $table, ?string $column): int|string
    {
        $tableName = $this->normalizeCatalogName($table);
        $columnName = $column !== null && $column !== '' ? $this->normalizeCatalogName($column) : null;

        $this->connect();

        if ($this->useAutoincrement()) {
            try {
                $sql = 'SELECT sequence_name FROM user_tab_identity_cols WHERE table_name = :p_table';
                $params = [':p_table' => $tableName];
                if ($columnName !== null) {
                    $sql .= ' AND column_name = :p_column';
                    $params[':p_column'] = $columnName;
                }

                $seqStatement = $this->getPdo()->prepare($sql);
                $seqStatement->execute($params);
                $result = $seqStatement->fetch(PDO::FETCH_NUM);
                if ($result !== false && !empty($result[0])) {
                    $currval = $this->fetchSequenceCurrval((string)$result[0]);
                    if ($currval !== null) {
                        return $currval;
                    }
                }
            } catch (Throwable) {
            }
        }

        $sequenceCandidates = [];
        if ($this->isAutoQuotingEnabled()) {
            $sequenceCandidates[] = 'seq_' . $tableName;
        }

        $sequenceCandidates[] = 'SEQ_' . strtoupper($tableName);

        foreach ($sequenceCandidates as $sequenceName) {
            $currval = $this->fetchSequenceCurrval($sequenceName);
            if ($currval !== null) {
                return $currval;
            }
        }

        $maxId = $this->fetchLastInsertIdFromMax($table, $column);
        if ($maxId !== null) {
            return $maxId;
        }

        return 0;
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool
    {
        if (!$this->pdo instanceof PDO) {
            return false;
        }

        try {
            return (bool)$this->pdo->query('SELECT 1 FROM DUAL');
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Quotes identifier in case automatic quote enabled for driver.
     *
     * @param string $identifier The identifier to quote.
     * @return string
     */
    public function quoteIfAutoQuote(string $identifier): string
    {
        if ($this->isAutoQuotingEnabled()) {
            return $this->quoteIdentifier($identifier);
        }

        return $identifier;
    }

    /**
     * Show if driver supports oci layer calls.
     *
     * @return bool
     */
    public function isOci(): bool
    {
        return false;
    }

    /**
     * Returns whether query logging is enabled on this driver.
     *
     * @return bool
     */
    public function isQueryLoggingEnabled(): bool
    {
        return $this->logQueries;
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
        throw new NotImplementedException(__('method not implemented for this driver'));
    }

    /**
     * @inheritDoc
     */
    public function getMaxAliasLength(): ?int
    {
        if ($this->_serverVersion !== null && $this->_serverVersion >= 12.2) {
            return static::MAX_ALIAS_LENGTH12;
        }

        return static::MAX_ALIAS_LENGTH;
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Database\QueryCompiler
     */
    public function newCompiler(): QueryCompiler
    {
        if ($this->_serverVersion !== null && $this->_serverVersion >= 12) {
            return new Oracle12Compiler();
        }

        return new OracleCompiler();
    }

    /**
     * @inheritDoc
     */
    public function schema(): string
    {
        return (string)($this->_config['database'] ?? '');
    }

    /**
     * @inheritDoc
     */
    public function supports(DriverFeatureEnum $feature): bool
    {
        $version = $this->_serverVersion ?? 11;

        return match ($feature) {
            DriverFeatureEnum::DISABLE_CONSTRAINT_WITHOUT_TRANSACTION,
            DriverFeatureEnum::SAVEPOINT,
            DriverFeatureEnum::INTERSECT,
            DriverFeatureEnum::INTERSECT_ALL,
            DriverFeatureEnum::SET_OPERATIONS_ORDER_BY,
            DriverFeatureEnum::OPTIMIZER_HINT_COMMENT => true,
            DriverFeatureEnum::TRUNCATE_WITH_CONSTRAINTS => true,
            DriverFeatureEnum::JSON => false,
            DriverFeatureEnum::CTE,
            DriverFeatureEnum::WINDOW,
            DriverFeatureEnum::CHECK_CONSTRAINTS => $version >= 12,
            default => false,
        };
    }
}
