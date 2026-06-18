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
namespace CakeDC\OracleDriver\Database;

use Cake\Database\Driver;
use Cake\Database\TypeFactory;
use Cake\Database\TypeInterface;

/**
 * Type converter trait for PL/SQL request binding.
 */
trait TypeConverterTrait
{
    /**
     * Converts a value to a database value and statement parameter type.
     *
     * @param mixed $value The value to cast.
     * @param \Cake\Database\TypeInterface|string $type The type name or type instance to use.
     * @return array{0: mixed, 1: int}
     */
    public function cast(mixed $value, TypeInterface|string $type = 'string'): array
    {
        if (is_string($type)) {
            $type = TypeFactory::build($type);
        }
        if ($type instanceof TypeInterface) {
            $value = $type->toDatabase($value, $this->_driver);
            $type = $type->toStatement($value, $this->_driver);
        }

        return [$value, $type];
    }

    /**
     * Matches columns to corresponding types.
     *
     * @param array $columns List or associative array of columns.
     * @param array $types List or associative array of types.
     * @return array
     */
    public function matchTypes(array $columns, array $types): array
    {
        if (!is_int(key($types))) {
            $positions = array_intersect_key(array_flip($columns), $types);
            $types = array_intersect_key($types, $positions);
            $types = array_combine($positions, $types);
        }

        return $types;
    }
}
