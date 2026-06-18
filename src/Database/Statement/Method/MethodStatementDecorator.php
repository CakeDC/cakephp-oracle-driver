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
     * Binds a value by reference for OCI-compatible parameter binding.
     *
     * @param string|int $column Name or positional index of the parameter.
     * @param mixed $value The value to bind by reference.
     * @param string|int $type OCI type or configured Type class name.
     * @return void
     */
    public function bindParam(string|int $column, mixed &$value, string|int $type = 'string'): void
    {
        $this->_statement->bindParam($column, $value, $type);
    }
}
