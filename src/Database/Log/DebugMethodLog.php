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
    protected array $_queries = [];

    /**
     * Decorated logger.
     *
     * @var \CakeDC\OracleDriver\Database\Log\MethodLogger
     */
    protected ?MethodLogger $_logger = null;

    /**
     * Name of the connection being logged.
     *
     * @var string
     */
    protected string $_connectionName;

    /**
     * Total time (ms) of all queries
     *
     * @var float|int
     */
    protected int|float $_totalTime = 0;

    /**
     * Total rows of all queries
     *
     * @var int
     */
    protected int $_totalRows = 0;

    /**
     * Constructor
     *
     * @param \CakeDC\OracleDriver\Database\Log\MethodLogger $logger The logger to decorate and spy on.
     * @param string $name The name of the connection being logged.
     */
    public function __construct(?MethodLogger $logger, string $name)
    {
        $this->_logger = $logger;
        $this->_connectionName = $name;
    }

    /**
     * Get the stored logs.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->_connectionName;
    }

    /**
     * Get the stored logs.
     *
     * @return array
     */
    public function queries(): array
    {
        return $this->_queries;
    }

    /**
     * Get the total time
     *
     * @return float|int
     */
    public function totalTime(): int|float
    {
        return $this->_totalTime;
    }

    /**
     * Get the total rows
     *
     * @return int
     */
    public function totalRows(): int
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
