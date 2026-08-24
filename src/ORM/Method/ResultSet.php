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
namespace CakeDC\OracleDriver\ORM\Method;

use ArrayAccess;
use Cake\Collection\CollectionTrait;
use Cake\Database\Driver;
use Cake\Database\Exception\DatabaseException;
use Cake\Database\StatementInterface;
use Cake\Database\TypeFactory;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Entity;
use CakeDC\OracleDriver\ORM\Method;
use SplFixedArray;

/**
 * Represents the results obtained after executing a query for a specific cursor returned by method call.
 */
class ResultSet implements ResultSetInterface
{
    use CollectionTrait;

    /**
     * Database statement holding the results
     *
     * @var \Cake\Database\StatementInterface|null
     */
    protected ?StatementInterface $_statement = null;

    /**
     * Points to the next record number that should be fetched
     *
     * @var int
     */
    protected int $_index = 0;

    /**
     * Last record fetched from the statement
     *
     * @var array|object|false
     */
    protected mixed $_current = false;

    /**
     * Results that have been fetched or hydrated into the results.
     *
     * @var \ArrayAccess|array
     */
    protected array|ArrayAccess $_results = [];

    /**
     * Whether to hydrate results into objects or not
     *
     * @var bool
     */
    protected bool $_hydrate = true;

    /**
     * The fully namespaced name of the class to use for hydrating results
     *
     * @var string
     */
    protected string $_entityClass;

    /**
     * Whether or not to buffer results fetched from the statement
     *
     * @var bool
     */
    protected bool $_useBuffering = false;

    /**
     * Holds the count of records in this result set
     *
     * @var int|null
     */
    protected ?int $_count = null;

    /**
     * Type cache for type converters.
     *
     * Converters are indexed by alias and column name.
     */
    protected array $_types;

    /**
     * Type schema for cursor result.
     *
     * @var array
     */
    protected array $_schema = [];

    /**
     * The Database driver object.
     *
     * Cached in a property to avoid multiple calls to the same function.
     *
     * @var \Cake\Database\Driver
     */
    protected Driver $_driver;

    /**
     * Constructor
     *
     * @param \CakeDC\OracleDriver\ORM\Method $repository Method object instance.
     * @param \Cake\Database\StatementInterface $statement The statement to fetch from
     * @param array $options Additional resultset options that setup result entity.
     * @internal param \Cake\ORM\Query $query Query from where results come
     */
    public function __construct(Method $repository, StatementInterface $statement, array $options = [])
    {
        $options += [
            'entityClass' => Entity::class,
            'hydrate' => true,
            'useBuffering' => false,
            'schema' => [],
        ];
        $this->_statement = $statement;
        $this->_driver = $repository->getConnection()->getDriver();
        $this->_hydrate = $options['hydrate'];
        $this->_entityClass = $options['entityClass'];
        $this->_useBuffering = $options['useBuffering'];
        $this->_schema = $options['schema'];
        $this->_types = $this->_getTypes(array_keys($this->_schema));

        if ($this->_useBuffering) {
             $count = $this->count();
             $this->_results = new SplFixedArray($count);
        }
    }

    /**
     * Returns the current record in the result iterator
     *
     * Part of Iterator interface.
     *
     * @return object|array|false
     */
    public function current(): mixed
    {
        return $this->_current;
    }

    /**
     * Returns the key of the current record in the iterator
     *
     * Part of Iterator interface.
     *
     * @return int
     */
    public function key(): int
    {
        return $this->_index;
    }

    /**
     * Advances the iterator pointer to the next record
     *
     * Part of Iterator interface.
     *
     * @return void
     */
    public function next(): void
    {
        $this->_index++;
    }

    /**
     * Rewinds a ResultSet.
     *
     * Part of Iterator interface.
     *
     * @throws \Cake\Database\Exception\DatabaseException
     * @return void
     */
    public function rewind(): void
    {
        if ($this->_index === 0) {
            return;
        }

        if (!$this->_useBuffering) {
            $msg = 'You cannot rewind an un-buffered ResultSet';
            throw new DatabaseException($msg);
        }

        $this->_index = 0;
    }

    /**
     * Whether there are more results to be fetched from the iterator
     *
     * Part of Iterator interface.
     *
     * @return bool
     */
    public function valid(): bool
    {
        if ($this->_useBuffering) {
            $valid = $this->_index < $this->_count;
            if ($valid && $this->_results[$this->_index] !== null) {
                $this->_current = $this->_results[$this->_index];

                return true;
            }

            if (!$valid) {
                return $valid;
            }
        }

        $this->_current = $this->_fetchResult();
        $valid = $this->_current !== false;

        if ($valid && $this->_useBuffering) {
            $this->_results[$this->_index] = $this->_current;
        }

        if (!$valid && $this->_statement !== null) {
            $this->_statement->closeCursor();
        }

        return $valid;
    }

    /**
     * Helper function to fetch the next result from the statement or
     * seeded results.
     *
     * @return false|object|array
     */
    protected function _fetchResult(): false|object|array
    {
        if (!$this->_statement) {
            return false;
        }

        $row = $this->_statement->fetch('assoc');
        if ($row === false) {
            return $row;
        }

        return $this->_groupResult($row);
    }

    /**
     * Correctly nests results keys including those coming from associations
     *
     * @param array $row Array containing columns and values
     * @return object|array Results
     */
    protected function _groupResult(array $row): object|array
    {
        $results = $this->_castValues($row);
        $options = [];
        if ($this->_hydrate) {
            return new $this->_entityClass($results, $options);
        }

        return $results;
    }

    /**
     * Get the first record from a result set.
     *
     * This method will also close the underlying statement cursor.
     *
     * @return object|array|false
     */
    public function first(): mixed
    {
        foreach ($this as $result) {
            if ($this->_statement && !$this->_useBuffering) {
                $this->_statement->closeCursor();
            }

            return $result;
        }

        return false;
    }

    /**
     * Serializes a resultset.
     *
     * Part of Serializable interface.
     *
     * @return string Serialized object
     */
    public function serialize(): string
    {
        while ($this->valid()) {
            $this->next();
        }

        return serialize($this->_results);
    }

    /**
     * Unserializes a resultset.
     *
     * Part of Serializable interface.
     *
     * @param string $serialized Serialized object
     * @return void
     */
    public function unserialize(string $serialized): void
    {
        $this->_results = unserialize($serialized);
        $this->_useBuffering = true;
        $this->_count = is_countable($this->_results) ? count($this->_results) : 0;
    }

    /**
     * Gives the number of rows in the result set.
     *
     * Part of the Countable interface.
     *
     * @return int
     */
    public function count(): int
    {
        if ($this->_count !== null) {
            return $this->_count;
        }

        if ($this->_statement) {
            return $this->_count = $this->_statement->rowCount();
        }

        return $this->_count = count($this->_results);
    }

    /**
     * Casts all values from a row brought from a table to the correct
     * PHP type.
     *
     * @param array $values The values to cast
     * @return array
     */
    protected function _castValues(array $values): array
    {
        foreach ($this->_types as $field => $type) {
            $values[$field] = $type->toPHP($values[$field], $this->_driver);
        }

        return $values;
    }

    /**
     * Returns the Type classes for each of the passed fields
     * belonging to the cursor result.
     *
     * @param array $fields The fields whitelist to use for fields in the schema.
     * @return array
     */
    protected function _getTypes(array $fields): array
    {
        $types = [];
        $schema = $this->_schema;
        $map = array_keys((array)TypeFactory::getMap() + ['string' => 1, 'text' => 1, 'boolean' => 1]);
        $typeMap = array_combine(
            $map,
            array_map(['Cake\Database\Type', 'build'], $map),
        );

        foreach (['string', 'text'] as $t) {
            if ($typeMap[$t] instanceof TypeFactory) {
                unset($typeMap[$t]);
            }
        }

        foreach (array_intersect($fields, array_keys($schema)) as $col) {
            $typeName = $schema[$col];
            if (isset($typeMap[$typeName])) {
                $types[$col] = $typeMap[$typeName];
            }
        }

        return $types;
    }

    /**
     * Returns an array that can be used to describe the internal state of this
     * object.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'items' => $this->toArray(),
        ];
    }
}
