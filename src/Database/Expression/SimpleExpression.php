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
namespace CakeDC\OracleDriver\Database\Expression;

use Cake\Database\ExpressionInterface;
use Cake\Database\ValueBinder;
use Closure;

/**
 * This class represents a function call string in a SQL statement. Calls can be
 * constructed by passing the name of the function and a list of params.
 * For security reasons, all params passed are quoted by default unless
 * explicitly told otherwise.
 *
 * @internal
 */
class SimpleExpression implements ExpressionInterface
{
    /**
     * @var string
     */
    protected string $_returnType;

    /**
     * The name of the function to be constructed when generating the SQL string
     *
     * @var string
     */
    protected string $_name;

    /**
     * Constructor. Takes a name for the function to be invoked.
     *
     * ### Examples:
     *
     *  ``$f = new SimpleExpression('ROWID');``
     *
     * Previous line will generate ``ROWID``
     *
     * @param string $name the name of the function to be constructed
     * @param string $returnType The return type of this expression
     */
    public function __construct(string $name, string $returnType = 'string')
    {
        $this->_name = $name;
        $this->_returnType = $returnType;
    }

    /**
     * Sets the name of the SQL function to be invoke in this expression,
     * if no value is passed it will return current name
     *
     * @param string $name The name of the function
     * @return $this|string
     */
    public function name(?string $name = null): string|self
    {
        if ($name === null) {
            return $this->_name;
        }

        $this->_name = $name;

        return $this;
    }

    /**
     * Returns the string representation of this object so that it can be used in a
     * SQL query. Note that values condition values are not included in the string,
     * in their place placeholders are put and can be replaced by the quoted values
     * accordingly.
     *
     * @param \Cake\Database\ValueBinder $generator Placeholder generator object
     * @return string
     */
    public function sql(ValueBinder $generator): string
    {
        return $this->_name;
    }

    /**
     * This method is a no-op, this is a leaf type of expression,
     * hence there is nothing to traverse
     *
     * @param \Closure $callable The callable to traverse with.
     * @return static
     */
    public function traverse(Closure $callable): static
    {
        return $this;
    }
}
