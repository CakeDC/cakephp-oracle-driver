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

use Cake\Collection\CollectionTrait;
use Cake\Database\Exception;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use SplFixedArray;

/**
 * Represents the results obtained after executing a query for a specific cursor returned by method call.
 *
 */
class ResultSet implements ResultSetInterface
{
    use CollectionTrait;

    /**
     * Database statement holding the results
     *
     * @var \Cake\Database\StatementInterface
     */
    protected $_statement;

    /**
     * Points to the next record number that should be fetched
     *
     * @var int
     */
    protected $_index = 0;

    /**
     * Last record fetched from the statement
     *
     * @var array
     */
    protected $_current;

    /**
     * Results that have been fetched or hydrated into the results.
     *
     * @var array|\ArrayAccess
     */
    protected $_results = [];

    /**
     * Whether to hydrate results into objects or not
     *
     * @var bool
     */
    protected $_hydrate = true;

    /**
     * The fully namespaced name of the class to use for hydrating results
     *
     * @var string
     */
    protected $_entityClass;

    /**
     * Whether or not to buffer results fetched from the statement
     *
     * @var bool
     */
    protected $_useBuffering = false;

    /**
     * Holds the count of records in this result set
     *
     * @var int
     */
    protected $_count;

    /**
     * Type cache for type converters.
     *
     * Converters are indexed by alias and column name.
     *
     * @var array
     */
    protected $_types = [];

    /**
     * Type schema for cursor result.
     *
     * @var array
     */
    protected $_schema = [];

    /**
     * The Database driver object.
     *
     * Cached in a property to avoid multiple calls to the same function.
     *
     * @var \Cake\Database\Driver
     */
    protected $_driver;

    /**
     * Constructor
     *
     * @param \CakeDC\OracleDriver\ORM\Method $repository Method object instance.
     * @param \Cake\Database\StatementInterface $statement The statement to fetch from
     * @param array $options Additional resultset options that setup result entity.
     * @internal param \Cake\ORM\Query $query Query from where results come
     */
    public function __construct($repository, $statement, $options = [])
    {
        $options += [
            'entityClass' => 'Cake\ORM\Entity',
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
     * @return array|object
     */
    public function current()
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
    public function key()
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
    public function next()
    {
        $this->_index++;
    }

    /**
     * Rewinds a ResultSet.
     *
     * Part of Iterator interface.
     *
     * @throws \Cake\Database\Exception
     * @return void
     */
    public function rewind()
    {
        if ($this->_index === 0) {
            return;
        }

        if (!$this->_useBuffering) {
            $msg = 'You cannot rewind an un-buffered ResultSet';
            throw new Exception($msg);
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
    public function valid()
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
     * @return mixed
     */
    protected function _fetchResult()
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
     * @param mixed $row Array containing columns and values or false if there is no results
     * @return array Results
     */
    protected function _groupResult($row)
    {
        $results = $this->_castValues($row);
        $options = [];
        if ($this->_hydrate && !($results instanceof EntityInterface)) {
            $results = new $this->_entityClass($results, $options);
        }

        return $results;
    }

    /**
     * Get the first record from a result set.
     *
     * This method will also close the underlying statement cursor.
     *
     * @return array|object
     */
    public function first()
    {
        foreach ($this as $result) {
            if ($this->_statement && !$this->_useBuffering) {
                $this->_statement->closeCursor();
            }

            return $result;
        }
    }

    /**
     * Serializes a resultset.
     *
     * Part of Serializable interface.
     *
     * @return string Serialized object
     */
    public function serialize()
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
    public function unserialize($serialized)
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
    protected function _castValues($values)
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
    protected function _getTypes($fields)
    {
        $types = [];
        $schema = $this->_schema;
        $map = array_keys(\Cake\Database\TypeFactory::getMap() + ['string' => 1, 'text' => 1, 'boolean' => 1]);
        $typeMap = array_combine(
            $map,
            array_map(['Cake\Database\Type', 'build'], $map)
        );

        foreach (['string', 'text'] as $t) {
            if ($typeMap[$t] instanceof \Cake\Database\TypeFactory) {
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
    public function __debugInfo()
    {
        return [
            'items' => $this->toArray(),
        ];
    }
}
