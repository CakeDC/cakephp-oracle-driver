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
namespace CakeDC\OracleDriver\ORM;

use ArrayAccess;
use JsonSerializable;

/**
 * Describes the methods that any class representing a data storage should
 * comply with.
 */
interface RequestInterface extends ArrayAccess, JsonSerializable
{
    /**
     * Sets one or multiple properties to the specified value
     *
     * @param array|string $property the name of property to set or a list of
     * properties with their respective values
     * @param mixed $value The value to set to the property or an array if the
     * first argument is also an array, in which case will be treated as $options
     * @param array $options options to be used for setting the property. Allowed option
     * keys are `setter` and `guard`
     * @return \CakeDC\OracleDriver\ORM\RequestInterface
     */
    public function set(string|array $property, mixed $value = null, array $options = []): RequestInterface;

    /**
     * Returns the value of a property by name
     *
     * @param string $property the name of the property to retrieve
     * @return mixed
     */
    public function &get(string $property): mixed;

    /**
     * Returns an array with all the properties that have been set
     * to this entity
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Returns whether or not this entity has already been persisted.
     * This method can return null in the case there is no prior information on
     * the status of this entity.
     *
     * If called with a boolean, this method will set the status of this instance.
     * Using `true` means that the instance has not been persisted in the database, `false`
     * that it already is.
     *
     * @param bool|null $new Indicate whether or not this instance has been persisted.
     * @return bool If it is known whether the entity was already persisted
     * null otherwise
     */
    public function isNew(mixed $new = null): bool;
}
