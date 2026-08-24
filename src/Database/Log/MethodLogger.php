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

use Cake\Log\Log;

class MethodLogger
{
    /**
     * Writes a LoggedMethod into a log
     *
     * @param \CakeDC\OracleDriver\Database\Log\LoggedMethod $method to be written in log
     * @return void
     */
    public function log(LoggedMethod $method): void
    {
        if ($method->params !== []) {
            $method->method = $this->_interpolate($method);
        }

        $this->_log($method);
    }

    /**
     * Wrapper function for the logger object, useful for unit testing
     * or for overriding in subclasses.
     *
     * @param \CakeDC\OracleDriver\Database\Log\LoggedMethod $method to be written in log
     * @return void
     */
    protected function _log(LoggedMethod $method): void
    {
        Log::write('debug', print_r($method, true), ['queriesLog']);
    }

    /**
     * Helper function used to replace method placeholders by the real
     * params used to execute the method
     *
     * @param \CakeDC\OracleDriver\Database\Log\LoggedMethod $method The method to log
     * @return string
     */
    protected function _interpolate(LoggedMethod $method): ?string
    {
        $params = array_map(function ($p) {
            if ($p === null) {
                return 'NULL';
            }

            if (is_bool($p)) {
                return $p ? '1' : '0';
            }

            return is_string($p) ? "'$p'" : $p;
        }, $method->params);

        $keys = [];
        $limit = is_int(key($params)) ? 1 : -1;
        foreach ($params as $key => $param) {
            if ($param == 'NULL' || $key == ':result') {
                unset($params[$key]);
                continue;
            }

            $keys[] = is_string($key) ? "/$key\b/" : '/[?]/';
        }

        return preg_replace($keys, $params, $method->method, $limit);
    }
}
