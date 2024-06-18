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
namespace CakeDC\OracleDriver\ORM\Exception;

use Cake\Core\Exception\CakeException;

/**
 * Exception raised when an Request could not be found.
 *
 */
class MissingRequestException extends CakeException
{
    protected $_messageTemplate = 'Request class %s could not be found.';
}
