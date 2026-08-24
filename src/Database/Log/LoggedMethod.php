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

use Exception;
use Stringable;

/**
 * Contains a method string, the params used to executed it, time taken to do it
 * and the number of rows found or affected by its execution.
 */
class LoggedMethod implements Stringable
{
    /**
     * Method query string that was executed
     *
     * @var string
     */
    public string $method = '';

    /**
     * Number of milliseconds this method took to complete
     *
     * @var float
     */
    public float $took = 0;

    /**
     * Associative array with the params bound to the method string
     *
     * @var string
     */
    public string $params = [];

    /**
     * Number of rows affected or returned by the method execution
     *
     * @var int
     */
    public int $numRows = 0;

    /**
     * The exception that was thrown by the execution of this method
     *
     * @var \Exception
     */
    public Exception $error;

    /**
     * Returns the string representation of this logged method
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->method;
    }
}
