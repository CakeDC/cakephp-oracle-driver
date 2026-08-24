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
     * The SQL query string associated with the error, when available.
     *
     * @var string|null
     */
    public ?string $queryString = null;

    /**
     * OCI Error builder.
     *
     * @param array $error Error information that includes error message and code.
     * @return self
     */
    public static function fromErrorInfo(array $error): self
    {
        return new self($error['message'], $error['code']);
    }
}
