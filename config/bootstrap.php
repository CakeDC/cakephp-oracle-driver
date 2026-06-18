<?php
declare(strict_types=1);

/**
 * Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

use Cake\Database\TypeFactory;

TypeFactory::map('cursor', 'CakeDC\OracleDriver\Database\Type\CursorType');
TypeFactory::map('boolean', 'CakeDC\OracleDriver\Database\Type\BoolType');
