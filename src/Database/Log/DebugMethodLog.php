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
namespace CakeDC\OracleDriver\Database\Log;

/**
 * DebugKit Method logger.
 *
 * This logger decorates the existing logger if it exists,
 * and stores log messages internally so they can be displayed
 * or stored for future use.
 */
class DebugMethodLog extends MethodLogger
{
    /**
     * Logs from the current request.
     *
     * @var array
     */
    protected $_queries = [];

    /**
     * Decorated logger.
     *
     * @var \CakeDC\OracleDriver\Database\Log\LoggedMethod
     */
    protected $_logger;

    /**
     * Name of the connection being logged.
     *
     * @var string
     */
    protected $_connectionName;

    /**
     * Total time (ms) of all queries
     *
     * @var int
     */
    protected $_totalTime = 0;

    /**
     * Total rows of all queries
     *
     * @var int
     */
    protected $_totalRows = 0;

    /**
     * Constructor
     *
     * @param \CakeDC\OracleDriver\Database\Log\MethodLogger $logger The logger to decorate and spy on.
     * @param string $name The name of the connection being logged.
     */
    public function __construct($logger, $name)
    {
        $this->_logger = $logger;
        $this->_connectionName = $name;
    }

    /**
     * Get the stored logs.
     *
     * @return array
     */
    public function name()
    {
        return $this->_connectionName;
    }

    /**
     * Get the stored logs.
     *
     * @return array
     */
    public function queries()
    {
        return $this->_queries;
    }

    /**
     * Get the total time
     *
     * @return int
     */
    public function totalTime()
    {
        return $this->_totalTime;
    }

    /**
     * Get the total rows
     *
     * @return int
     */
    public function totalRows()
    {
        return $this->_totalRows;
    }

    /**
     * Log queries
     *
     * @param \CakeDC\OracleDriver\Database\Log\LoggedMethod $method The query being logged.
     * @return void
     */
    public function log(LoggedMethod $method): void
    {
        if ($this->_logger) {
            $this->_logger->log($method);
        }
        
        if (!empty($method->params)) {
            $method->method = $this->_interpolate($method);
        }
        
        $this->_totalTime += $method->took;
        $this->_totalRows += $method->numRows;

        $this->_queries[] = [
            'method' => $method->method,
            'took' => $method->took,
            'rows' => $method->numRows,
        ];
    }
}
