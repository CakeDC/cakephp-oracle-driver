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
namespace CakeDC\OracleDriver\ORM\Locator;

use CakeDC\OracleDriver\ORM\Method;

/**
 * Registries for Method objects should implement this interface.
 */
interface LocatorInterface
{
    /**
     * Stores a list of options to be used when instantiating an object
     * with a matching alias.
     *
     * @param array|string|null $alias Name of the alias
     * @param array|null $options list of options for the alias
     * @return array The config data.
     */
    public function config(string|array|null $alias = null, ?array $options = null): array;

    /**
     * Get a method instance from the registry.
     *
     * @param string $alias The alias name you want to get.
     * @param array $options The options you want to build the method with.
     * @return \CakeDC\OracleDriver\ORM\Method
     */
    public function get(string $alias, array $options = []): Method;

    /**
     * Check to see if an instance exists in the registry.
     *
     * @param string $alias The alias to check for.
     * @return bool
     */
    public function exists(string $alias): bool;

    /**
     * Set an instance.
     *
     * @param string $alias The alias to set.
     * @param \CakeDC\OracleDriver\ORM\Method $object The method to set.
     * @return \CakeDC\OracleDriver\ORM\Method
     */
    public function set(string $alias, Method $object): Method;

    /**
     * Clears the registry of configuration and instances.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Removes an instance from the registry.
     *
     * @param string $alias The alias to remove.
     * @return void
     */
    public function remove(string $alias): void;

    /**
     * Get all instantiated method objects.
     *
     * @return array<\CakeDC\OracleDriver\ORM\Method>
     */
    public function genericInstances(): array;
}
