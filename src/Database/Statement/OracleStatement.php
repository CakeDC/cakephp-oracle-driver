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
namespace CakeDC\OracleDriver\Database\Statement;

use Cake\Database\Statement\Statement;
use CakeDC\OracleDriver\Database\Driver\OracleBase;
use Generator;
use PDO;

/**
 * Statement class meant to be used by an Oracle driver.
 */
class OracleStatement extends Statement
{
    protected string $rawQueryString = '';

    /**
     * @var array<int|string, string|int>
     */
    protected array $paramMap = [];

    /**
     * @param string $sql Raw SQL before Oracle placeholder conversion.
     * @return void
     */
    public function setRawQueryString(string $sql): void
    {
        $this->rawQueryString = $sql;
    }

    /**
     * @param array<int|string, string|int> $paramMap Positional to named placeholder map.
     * @return void
     */
    public function setParamMap(array $paramMap): void
    {
        $this->paramMap = $paramMap;
    }

    /**
     * @inheritDoc
     */
    public function queryString(): string
    {
        if ($this->rawQueryString !== '') {
            return $this->rawQueryString;
        }

        return parent::queryString();
    }

    /**
     * @inheritDoc
     */
    protected function performBind(string|int $column, mixed $value, int $type): void
    {
        $column = $this->paramMap[$column] ?? $column;
        parent::performBind($column, $value, $type);
    }

    /**
     * @inheritDoc
     */
    public function fetch(string|int $mode = PDO::FETCH_NUM): mixed
    {
        $mode = $this->convertMode($mode);
        $row = $this->statement->fetch($mode);
        if ($row === false) {
            return false;
        }

        $row = $this->hydrateLobRow($row);
        foreach ($this->resultDecorators as $decorator) {
            $row = $decorator($row);
        }

        return $row;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string|int $mode = PDO::FETCH_NUM): array
    {
        $rows = [];
        while (($row = $this->fetch($mode)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Generator
    {
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);

        foreach ($this->statement as $row) {
            $row = $this->hydrateLobRow($row);
            foreach ($this->resultDecorators as $decorator) {
                $row = $decorator($row);
            }

            yield $row;
        }

        $this->closeCursor();
    }

    /**
     * Copy CLOB/BLOB locators into PHP strings before the next PDO_OCI fetch
     * reuses them.
     *
     * @param mixed $row Fetched row.
     * @return mixed
     */
    protected function hydrateLobRow(mixed $row): mixed
    {
        if (is_array($row)) {
            foreach ($row as &$value) {
                $value = $this->readLobValue($value);
            }
            unset($value);

            return $row;
        }

        if (is_object($row)) {
            foreach (get_object_vars($row) as $key => $value) {
                $row->{$key} = $this->readLobValue($value);
            }
        }

        return $row;
    }

    /**
     * @param mixed $value Value that may be a LOB resource or object.
     * @return mixed
     */
    protected function readLobValue(mixed $value): mixed
    {
        if (is_resource($value)) {
            $contents = stream_get_contents($value);

            return $contents === false ? $value : $contents;
        }

        if (is_object($value) && method_exists($value, 'load')) {
            $loaded = $value->load();
            if (is_string($loaded) || $loaded === null) {
                return $loaded;
            }
        }

        if (is_object($value) && method_exists($value, 'read') && method_exists($value, 'size')) {
            return $value->read($value->size());
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId(?string $table = null, ?string $column = null): string|int
    {
        if ($column && $this->columnCount()) {
            $row = $this->fetch(static::FETCH_TYPE_ASSOC);

            if ($row && isset($row[$column])) {
                return $row[$column];
            }
        }

        $driver = $this->_driver;
        if ($driver instanceof OracleBase) {
            return $driver->lastInsertId($table, $column);
        }

        return $driver->lastInsertId($table);
    }
}
