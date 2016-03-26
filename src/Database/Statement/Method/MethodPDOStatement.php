<?php
/**
 * Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\OracleDriver\Database\Statement\Method;

use PDO;
use PDOStatement as Statement;

/**
 * Decorator for \PDOStatement class mainly used for converting human readable
 * fetch modes into PDO constants.
 */
class MethodPDOStatement extends MethodStatementDecorator
{

    /**
     * Constructor
     *
     * @param \PDOStatement|null $statement Original statement to be decorated.
     * @param \Cake\Database\Driver|null $driver Driver instance.
     */
    public function __construct(Statement $statement = null, $driver = null)
    {
        $this->_statement = $statement;
        $this->_driver = $driver;
    }

    /**
     * Assign a value to a positional or named variable in prepared query. If using
     * positional variables you need to start with index one, if using named params then
     * just use the name in any order.
     * 
     * Parameters values are always passed by reference.
     *
     * You can pass OCI compatible constants for binding values with a type or optionally
     * any type name registered in the Type class. Any value will be converted to the valid type
     * representation if needed.
     *
     * It is not allowed to combine positional and named variables in the same statement
     *
     * ### Examples:
     *
     * ```
     * $val = 'a title';
     * $statement->bindParam(1, $val);
     * $boolVal = true;
     * $statement->bindParam('active', $boolVal, 'boolean');
     * $date = new \DateTime();
     * $statement->bindParam(5, $date, 'date');
     * ```
     *
     * @param string|int $column name or param position to be bound
     * @param mixed $value The value to bind to variable in query
     * @param string|int $type OCI type or name of configured Type class
     * @param int $length [optional]
     * Length of the data type. To indicate that a parameter is an OUT
     * parameter from a stored procedure, you must explicitly set the
     * length.
     * @return void
     */
    public function bindParam($column, &$value, $type = 'string', $length = null)
    {
        if ($type === null) {
            $type = 'string';
        }
        if (!ctype_digit((string)$type)) {
            list($value, $type) = $this->cast($value, $type);
        }
        $this->_statement->bindParam($column, $value, $type, $length);
    }

    /**
     * {@inheritDoc}
     */
    public function bindValue($column, $value, $type = 'string')
    {
        if ($type === null) {
            $type = 'string';
        }
        if (!ctype_digit($type)) {
            list($value, $type) = $this->cast($value, $type);
        }
        $this->_statement->bindValue($column, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function fetch($type = 'num')
    {
        if ($type === 'num') {
            return $this->_statement->fetch(PDO::FETCH_NUM);
        }
        if ($type === 'assoc') {
            return $this->_statement->fetch(PDO::FETCH_ASSOC);
        }
        return $this->_statement->fetch($type);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll($type = 'num')
    {
        if ($type === 'num') {
            return $this->_statement->fetchAll(PDO::FETCH_NUM);
        }
        if ($type === 'assoc') {
            return $this->_statement->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->_statement->fetchAll($type);
    }
}
