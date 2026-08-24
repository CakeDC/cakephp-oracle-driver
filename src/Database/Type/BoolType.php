<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link https://cakephp.org CakePHP(tm) Project
 * @since 3.1.2
 * @license https://opensource.org/licenses/mit-license.php MIT License
 */
namespace CakeDC\OracleDriver\Database\Type;

use Cake\Database\Driver;
use Cake\Database\Type\BaseType;
use Cake\Database\Type\BatchCastingInterface;
use InvalidArgumentException;
use PDO;

/**
 * Bool type converter.
 *
 * Use to convert bool data between PHP and the database types.
 */
class BoolType extends BaseType implements BatchCastingInterface
{
    /**
     * Identifier name for this type.
     *
     * (This property is declared here again so that the inheritance from
     * Cake\Database\Type can be removed in the future.)
     *
     * @var string|null
     */
    protected ?string $_name = null;

    /**
     * Constructor.
     *
     * (This method is declared here again so that the inheritance from
     * Cake\Database\Type can be removed in the future.)
     *
     * @param string|null $name The name identifying this type
     */
    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * Convert bool data into the database format.
     *
     * @param mixed $value The value to convert.
     * @param \Cake\Database\Driver $driver The driver instance to convert with.
     * @return int|bool|null
     */
    public function toDatabase(mixed $value, Driver $driver): mixed
    {
        if ($value === null) {
            return $value;
        }

        if ($value === true || $value === false) {
            return $value ? 1 : 0;
        }

        if (in_array($value, [1, 0, '1', '0'], true)) {
            return (int)$value;
        }

        throw new InvalidArgumentException(sprintf(
            'Cannot convert value of type `%s` to bool',
            get_debug_type($value),
        ));
    }

    /**
     * Convert bool values to PHP booleans
     *
     * @param mixed $value The value to convert.
     * @param \Cake\Database\Driver $driver The driver instance to convert with.
     * @return bool|null
     */
    public function toPHP(mixed $value, Driver $driver): ?bool
    {
        if (in_array($value, [null, true, false], true)) {
            return $value;
        }

        if (!is_numeric($value)) {
            return strtolower($value) === 'true';
        }

        return !empty($value);
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function manyToPHP(array $values, array $fields, Driver $driver): array
    {
        foreach ($fields as $field) {
            if (!isset($values[$field]) || $values[$field] === true || $values[$field] === false) {
                continue;
            }

            if ($values[$field] === '1') {
                $values[$field] = true;
                continue;
            }

            if ($values[$field] === '0') {
                $values[$field] = false;
                continue;
            }

            $value = $values[$field];
            if (!is_numeric($value)) {
                $values[$field] = strtolower($value) === 'true';
                continue;
            }

            $values[$field] = !empty($value);
        }

        return $values;
    }

    /**
     * Get the correct PDO binding type for bool data.
     *
     * @param mixed $value The value being bound.
     * @param \Cake\Database\Driver $driver The driver.
     * @return int
     */
    public function toStatement(mixed $value, Driver $driver): int
    {
        if ($value === null) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_INT;
    }

    /**
     * Marshalls request data into PHP booleans.
     *
     * @param mixed $value The value to convert.
     * @return bool|null Converted value.
     */
    public function marshal(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (!is_scalar($value)) {
            return null;
        }

        return !empty($value);
    }
}
