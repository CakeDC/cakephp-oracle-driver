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

use Cake\Database\Statement\StatementDecorator;
use Cake\Database\StatementInterface;
use Countable;
use IteratorAggregate;

/**
 * Represents a database statement. Statements contains queries that can be
 * executed multiple times by binding different values on each call. This class
 * also helps convert values to their valid representation for the corresponding
 * types.
 *
 * This class is but a decorator of an actual statement implementation, such as
 * PDOStatement.
 */
class MethodStatementDecorator extends StatementDecorator implements StatementInterface, Countable, IteratorAggregate
{

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
        $this->_statement->bindParam($column, $value, $type, $length);
    }

}
