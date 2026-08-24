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

use PDO;

/**
 * Statement class meant to be used by an Oracle driver
 */
class MethodOracleStatement extends MethodStatementDecorator
{
    public string $queryString;

    /**
     * Map of positional parameters to their named placeholder equivalents.
     *
     * @var array
     */
    public array $paramMap = [];

    /**
     * @inheritDoc
     */
    public function execute(?array $params = null): bool
    {
        return $this->_statement->execute($params);
    }

    /**
     * @inheritDoc
     */
    public function __get(string $property): mixed
    {
        if ($property === 'queryString') {
            return empty($this->queryString) ? $this->_statement->queryString : $this->queryString;
        }

        return $this->_statement->{$property};
    }

    /**
     * @inheritDoc
     */
    public function bind(array $params, array $types): void
    {
        if ($params === []) {
            return;
        }

        $annonymousParams = is_int(key($params));

        $offset = 0;

        foreach ($params as $index => $value) {
            $type = null;
            if (isset($types[$index])) {
                $type = $types[$index];
            }

            if ($annonymousParams) {
                $index += $offset;
            }

            $this->bindValue($index, $value, $type);
        }
    }

    /**
     * @inheritDoc
     */
    public function bindValue(string|int $column, mixed $value, string|int|null $type = 'string'): void
    {
        $column = $this->paramMap[$column] ?? $column;

        $type = $type === 'boolean' ? 'integer' : $type;

        $this->_statement->bindValue($column, $value, $type);
    }

    /**
     * @inheritDoc
     */
    public function fetch(string|int $mode = PDO::FETCH_NUM): mixed
    {
        $result = $this->_statement->fetch($mode);
        if (is_array($result)) {
            foreach ($result as &$value) {
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string|int $mode = PDO::FETCH_NUM): array
    {
        return $this->_statement->fetchAll($mode);
    }
}
