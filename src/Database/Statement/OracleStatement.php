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
        $row = parent::fetch($mode);
        if (is_array($row)) {
            foreach ($row as &$value) {
                $value = $this->readLobValue($value);
            }
        }

        return $row;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string|int $mode = PDO::FETCH_NUM): array
    {
        $rows = parent::fetchAll($mode);
        foreach ($rows as &$row) {
            foreach ($row as &$value) {
                $value = $this->readLobValue($value);
            }
        }

        return $rows;
    }

    /**
     * @param mixed $value Value that may be a LOB resource or object.
     * @return mixed
     */
    protected function readLobValue(mixed $value): mixed
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }
        if (is_object($value) && method_exists($value, 'load')) {
            return $value->load();
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
