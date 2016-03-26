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

namespace CakeDC\OracleDriver\Database\Log;

use CakeDC\OracleDriver\Database\Statement\Method\MethodStatementDecorator;
use Exception;

/**
 * Statement decorator used to
 */
class MethodLoggingStatement extends MethodStatementDecorator
{

    /**
     * Logger instance responsible for actually doing the logging task
     *
     * @var MethodLogger
     */
    protected $_logger;

    /**
     * Holds bound params
     *
     * @var array
     */
    protected $_compiledParams = [];

    /**
     * Wrapper for the execute function to calculate time spent
     * and log the method afterwards.
     *
     * @param array $params List of values to be bound to method
     * @return bool True on success, false otherwise
     * @throws \Exception Re-throws any exception raised during method execution.
     */
    public function execute($params = null)
    {
        $t = microtime(true);
        $method = new LoggedMethod();

        try {
            $result = parent::execute($params);
        } catch (Exception $e) {
            $e->queryString = $this->queryString;
            $method->error = $e;
            $this->_log($method, $params, $t);
            throw $e;
        }

        $method->numRows = $this->rowCount();
        $this->_log($method, $params, $t);
        return $result;
    }

    /**
     * Copies the logging data to the passed LoggedMethod and sends it
     * to the logging system.
     *
     * @param \CakeDC\OracleDriver\Database\Log\LoggedMethod $method The method to log.
     * @param array $params List of values to be bound to method.
     * @param float $startTime The microtime when the method was executed.
     * @return void
     */
    protected function _log($method, $params, $startTime)
    {
        $method->took = round((microtime(true) - $startTime) * 1000, 0);
        $method->params = $params ?: $this->_compiledParams;
        $method->method = $this->queryString;
        $this->logger()->log($method);
    }

    /**
     * Wrapper for bindValue function to gather each parameter to be later used
     * in the logger function.
     *
     * @param string|int $column Name or param position to be bound
     * @param mixed $value The value to bind to variable in method
     * @param string|int|null $type PDO type or name of configured Type class
     * @return void
     */
    public function bindValue($column, $value, $type = 'string')
    {
        parent::bindValue($column, $value, $type);
        if ($type === null) {
            $type = 'string';
        }
        if (!ctype_digit($type)) {
            $value = $this->cast($value, $type)[0];
        }
        $this->_compiledParams[$column] = $value;
    }

    /**
     * Sets the logger object instance. When called with no arguments
     * it returns the currently setup logger instance
     *
     * @param object|null $instance Logger object instance.
     * @return object Logger instance
     */
    public function logger($instance = null)
    {
        if ($instance === null) {
            return $this->_logger;
        }
        return $this->_logger = $instance;
    }

    /**
     * Wrapper for bindValue function to gather each parameter to be later used
     * in the logger function.
     *
     * @param string|int $column Name or param position to be bound
     * @param mixed $value The value to bind to variable in query
     * @param string|int|null $type PDO type or name of configured Type class
     * @param int $length [optional]
     * Length of the data type. To indicate that a parameter is an OUT
     * parameter from a stored procedure, you must explicitly set the
     * length.
     * @return void
     */
    public function bindParam($column, &$value, $type = 'string', $length = null)
    {
        parent::bindParam($column, $value, $type, $length);
        if ($type === null) {
            $type = 'string';
        }
        if (!ctype_digit((string)$type)) {
            $value = $this->cast($value, $type)[0];
        }
        $this->_compiledParams[$column] = $value;
    }

}
