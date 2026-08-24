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
namespace CakeDC\OracleDriver\Database\Statement\Method;

use Cake\Database\Driver;
use Cake\Database\StatementInterface;
use CakeDC\OracleDriver\Database\TypeConverterTrait;
use Countable;
use IteratorAggregate;
use PDO;
use Traversable;

/**
 * Decorator base class for Oracle method statements.
 *
 * Replaces the removed Cake\Database\Statement\StatementDecorator (CakePHP 5).
 * Delegates all StatementInterface calls to the wrapped inner statement.
 */
class StatementDecorator implements StatementInterface, Countable, IteratorAggregate
{
    use TypeConverterTrait;

    /**
     * @var mixed Wrapped statement (PDOStatement or StatementInterface).
     */
    protected mixed $_statement = null;

    /**
     * @var \Cake\Database\Driver|null
     */
    protected ?Driver $_driver = null;

    /**
     * The SQL query string for logging purposes.
     *
     * @var string
     */
    public string $queryString = '';

    /**
     * Whether execute() has been called.
     *
     * @var bool
     */
    protected bool $_hasExecuted = false;

    /**
     * @param \Cake\Database\StatementInterface|mixed $statement Wrapped statement.
     * @param \Cake\Database\Driver|null $driver Driver instance.
     */
    public function __construct(mixed $statement = null, ?Driver $driver = null)
    {
        $this->_statement = $statement;
        $this->_driver = $driver;
    }

    /**
     * @inheritDoc
     */
    public function bindValue(string|int $column, mixed $value, string|int|null $type = 'string'): void
    {
        $this->_statement->bindValue($column, $value, $type);
    }

    /**
     * @inheritDoc
     */
    public function closeCursor(): void
    {
        $this->_statement->closeCursor();
    }

    /**
     * @inheritDoc
     */
    public function columnCount(): int
    {
        return $this->_statement->columnCount();
    }

    /**
     * @inheritDoc
     */
    public function errorCode(): string
    {
        return (string)$this->_statement->errorCode();
    }

    /**
     * @inheritDoc
     */
    public function errorInfo(): array
    {
        return $this->_statement->errorInfo();
    }

    /**
     * @inheritDoc
     */
    public function execute(?array $params = null): bool
    {
        $this->_hasExecuted = true;

        return $this->_statement->execute($params);
    }

    /**
     * @inheritDoc
     */
    public function fetch(string|int $mode = PDO::FETCH_NUM): mixed
    {
        return $this->_statement->fetch($mode);
    }

    /**
     * @inheritDoc
     */
    public function fetchAssoc(): array
    {
        $result = $this->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn(int $position): mixed
    {
        $result = $this->fetch(PDO::FETCH_NUM);
        if (is_array($result) && isset($result[$position])) {
            return $result[$position];
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string|int $mode = PDO::FETCH_NUM): array
    {
        return $this->_statement->fetchAll($mode);
    }

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
        return $this->_statement->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function bind(array $params, array $types): void
    {
        if ($params === []) {
            return;
        }

        $anonymousParams = is_int(key($params));
        $offset = 1;
        foreach ($params as $index => $value) {
            $type = $types[$index] ?? null;
            if ($anonymousParams) {
                $index += $offset;
            }

            $this->bindValue($index, $value, $type);
        }
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId(?string $table = null, ?string $column = null): string|int
    {
        if ($column && $this->columnCount()) {
            $row = $this->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && isset($row[$column])) {
                return $row[$column];
            }
        }

        return $this->_driver->lastInsertId($table, $column);
    }

    /**
     * @inheritDoc
     */
    public function queryString(): string
    {
        if ($this->_statement instanceof StatementInterface) {
            return $this->_statement->queryString();
        }

        return $this->_statement->queryString ?? '';
    }

    /**
     * @inheritDoc
     */
    public function getBoundParams(): array
    {
        if ($this->_statement instanceof StatementInterface) {
            return $this->_statement->getBoundParams();
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        if (!$this->_hasExecuted) {
            $this->execute();
        }

        if ($this->_statement instanceof IteratorAggregate) {
            return $this->_statement->getIterator();
        }

        return $this->_statement;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->rowCount();
    }

    /**
     * Returns the inner wrapped statement.
     *
     * @return mixed
     */
    public function getInnerStatement(): mixed
    {
        return $this->_statement;
    }
}
