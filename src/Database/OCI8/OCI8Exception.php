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
namespace CakeDC\OracleDriver\Database\OCI8;

use Cake\Core\Exception\CakeException;

class OCI8Exception extends CakeException
{
    /**
     * OCI Error builder.
     *
     * @param array $error Error information that includes error message and code.
     * @return \CakeDC\OracleDriver\Database\OCI8\OCI8Exception
     */
    public static function fromErrorInfo($error)
    {
        stackTrace();
        return new self($error['message'], $error['code']);
    }
}
