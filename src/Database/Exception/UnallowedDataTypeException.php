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
namespace CakeDC\OracleDriver\Database\Exception;

use Cake\Core\Exception\CakeException;

class UnallowedDataTypeException extends CakeException
{
    /**
     * {@inheritDoc}
     */
    protected $_messageTemplate = 'Column type %s not supported.';
}
