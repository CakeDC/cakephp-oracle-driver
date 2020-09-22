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
use Cake\Database\Query;
use Cake\Database\QueryCompiler;
use Cake\Database\Statement\PDOStatement;
use Cake\Database\StatementInterface;
use Cake\Database\ValueBinder;
use Cake\Http\Exception\NotImplementedException;
use Cake\Log\Log;
use CakeDC\OracleDriver\Config\ConfigTrait;
use CakeDC\OracleDriver\Database\Dialect\OracleDialectTrait;
use CakeDC\OracleDriver\Database\Oracle12Compiler;
use CakeDC\OracleDriver\Database\OracleCompiler;
use CakeDC\OracleDriver\Database\Statement\OracleStatement;
use PDO;

abstract class OracleBase extends Driver
{
    use ConfigTrait;
    use OracleDialectTrait;

    /**
     * @var bool|mixed
     */
    public $connected;

    /**
     * Base configuration settings for MySQL driver
     *
     * @var array
     */
    protected $_baseConfig = [
        'persistent' => true,
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'cake',
        'port' => '1521',
        'flags' => [],
        'encoding' => 'utf8',
        'case' => 'lower',
        'timezone' => null,
        'init' => [],
        'server_version' => 11,
        'autoincrement' => false,
    ];

    protected $_defaultConfig = [];

    protected $_serverVersion = null;

    /**
     * @var bool
     */
    protected $_autoincrement;

    /**
     * @return bool
     */
    public function useAutoincrement(): bool
    {
        return $this->_autoincrement;
    }

    /**
     * OracleBase constructor.
     *
     * @param array $config Configuration settings.
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (array_key_exists('server_version', $config)) {
            $this->_serverVersion = $config['server_version'];
        }
        $this->_autoincrement = !empty($config['autoincrement']);
    }

    /**
     * Establishes a connection to the database server
     *
     * @return bool true on success
     */
    public function connect(): bool
    {
        if ($this->_connection) {
            return true;
        }
        $config = $this->_config;

        $config['init'][] = "ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS' NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS' NLS_TIMESTAMP_TZ_FORMAT='YYYY-MM-DD HH24:MI:SS'";

        $config['flags'] += [
            // PDO::ATTR_CASE => PDO::CASE_LOWER, // @todo move to config setting
            PDO::NULL_EMPTY_STRING => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => empty($config['persistent']) ? false : $config['persistent'],
            PDO::ATTR_ORACLE_NULLS => true,
        ];

        $dsn = $this->getDSN();
        $this->_connect($dsn, $config);

        if (!empty($config['init'])) {
            foreach ((array)$config['init'] as $command) {
                $this->getConnection()
                     ->exec($command);
            }
        }

        return true;
    }

    /**
     * Build DSN string in oracle connection format.
     *
     * @return string
     */
    public function getDSN()
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
     * @inheritDoc
     */
    public function supportsDynamicConstraints(): bool
    {
        return true;
        // TODO: Implement supportsDynamicConstraints() method.
    }

    /**
     * Prepares a sql statement to be executed
     *
     * @param string|\Cake\Database\Query $query The query to convert into a statement.
     * @return \Cake\Database\StatementInterface
     */
    public function prepare($query): StatementInterface
    {
        $this->connect();
        $isObject = ($query instanceof \Cake\ORM\Query) || ($query instanceof \Cake\Database\Query);
        $queryStringRaw = $isObject ? $query->sql() : $query;
        Log::write('debug', $queryStringRaw);
        // debug($queryStringRaw);
        $queryString = $this->_fromDualIfy($queryStringRaw);
        [$queryString, $paramMap] = self::convertPositionalToNamedPlaceholders($queryString);
        $innerStatement = $this->_connection->prepare($queryString);

        $statement = $this->_wrapStatement($innerStatement);
        $statement->queryString = $queryStringRaw;
        $statement->paramMap = $paramMap;

        $disableBuffer = false;
        $normalizedQuery = substr(strtolower(trim($queryString, " \t\n\r\0\x0B(")), 0, 6);
        if ($normalizedQuery !== 'select') {
            $disableBuffer = true;
        }
        if ($normalizedQuery == 'alter ') {
            $alt = true;
        }
        if ($normalizedQuery == 'create') {
            $cr = true;
        }

        if (
            $isObject
            && !$query->isBufferedResultsEnabled()
            || $disableBuffer
        ) {
                $statement->bufferResults(false);
        }

        return $statement;
    }

    /**
     * {@inheritDoc}
     */
    public function compileQuery(Query $query, ValueBinder $generator): array
    {
        if ($this->_serverVersion !== null && $this->_serverVersion >= 12) {
            $processor = new Oracle12Compiler();
        } else {
            $processor = new OracleCompiler();
        }

        $translator = $this->queryTranslator($query->type());
        $query = $translator($query);

        return [$query, $processor->compile($query, $generator)];
    }

    /**
     * Add "FROM DUAL" to SQL statements that are SELECT statements
     * with no FROM clause specified
     *
     * @param string $queryString query
     * @return string
     */
    protected function _fromDualIfy($queryString)
    {
        $statement = strtolower(trim($queryString));
        if (strpos($statement, 'select') !== 0 || preg_match('/\sfrom\s/', $statement)) {
            return $queryString;
        }

        return "{$queryString} FROM DUAL";
    }

    /**
     * Converts positional (?) into named placeholders (:param<num>).
     *
     * Oracle does not support positional parameters, hence this method converts all
     * positional parameters into artificially named parameters. Note that this conversion
     * is not perfect. All question marks (?) in the original statement are treated as
     * placeholders and converted to a named parameter.
     *
     * The algorithm uses a state machine with two possible states: InLiteral and NotInLiteral.
     * Question marks inside literal strings are therefore handled correctly by this method.
     * This comes at a cost, the whole sql statement has to be looped over.
     *
     * @param string $query The SQL statement to convert.
     *
     * @return string
     */
    public function convertPositionalToNamedPlaceholders($query)
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
    public function lastInsertId(?string $table = null, ?string $column = null)
    {
        if ($this->useAutoincrement()) {
            return $this->_autoincrementSequenceId($table, $column);
        } else {
            $sequenceName = 'seq_' . strtolower($table);
            $this->connect();
            $statement = $this->_connection->query("SELECT {$sequenceName}.CURRVAL FROM DUAL");
            $result = $statement->fetch(PDO::FETCH_NUM);
            if (count($result) === 0) {
                return $this->_autoincrementSequenceId($table, $column);
            }

            return $result[0];
        }
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool
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
     * Quotes identifier in case automatic quote enabled for driver.
     *
     * @param string $identifier The identifier to quote.
     * @return string
     */
    public function quoteIfAutoQuote($identifier)
    {
        if ($this->isAutoQuotingEnabled()) {
            return $this->quoteIdentifier($identifier);
        }

        return $identifier;
    }

    /**
     * Wrap statement into cakephp statements to provide additional functionality.
     *
     * @param \CakeDC\OracleDriver\Database\Driver\Statement $statement Original statement to wrap.
     * @return \CakeDC\OracleDriver\Database\Statement\OracleStatement
     */
    protected function _wrapStatement($statement)
    {
        return new OracleStatement(new PDOStatement($statement, $this), $this);
    }

    /**
     * Show if driver supports oci layer calls.
     *
     * @return bool
     */
    public function isOci()
    {
        return false;
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
        throw new NotImplementedException(__('method not implemented for this driver'));
    }

    /**
     * Returns last insert id by autoincrement sequence.
     *
     * @param string $table Table name.
     * @param string $column Column name
     * @return int
     */
    protected function _autoincrementSequenceId(?string $table, ?string $column)
    {
        if ($this->isAutoQuotingEnabled()) {
            $tableName = $table;
            $columnName = $column;
        } else {
            $tableName = strtoupper($table);
            $columnName = strtoupper($column);
        }
        $query = "select sequence_name from user_tab_identity_cols where table_name='$tableName' and column_name='$columnName'";
        $this->connect();
        $seqStatement = $this->_connection->query($query);
        $result = $seqStatement->fetch(PDO::FETCH_NUM);

        $sequenceName = $result[0];

        $statement = $this->_connection->query("SELECT {$sequenceName}.CURRVAL FROM DUAL");
        $result = $statement->fetch(PDO::FETCH_NUM);

        return $result[0];
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
        } else {
            return new OracleCompiler();
        }
    }
}
