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

use CakeDC\OracleDriver\ORM\Locator\LocatorInterface;
use CakeDC\OracleDriver\ORM\Locator\MethodLocator;

/**
 * Provides a registry/factory for Method objects.
 *
 * This registry allows you to centralize the configuration for methods
 * their connections and other meta-data.
 *
 * ### Configuring instances
 *
 * You may need to configure your method objects, using MethodRegistry you can
 * centralize configuration. Any configuration set before instances are created
 * will be used when creating instances. If you modify configuration after
 * an instance is made, the instances *will not* be updated.
 *
 * ```
 * MethodRegistry::config('PackageFunction', ['method' => 'package.function']);
 * ```
 *
 * ### Getting instances
 *
 * You can fetch instances out of the registry using get(). One instance is stored
 * per alias. Once an alias is populated the same instance will always be returned.
 *
 * ```
 * $method = MethodRegistry::get('Procedure', $config);
 * ```
 */
class MethodRegistry
{
    /**
     * LocatorInterface implementation instance.
     *
     * @var \Cake\ORM\Locator\LocatorInterface
     */
    protected static \Cake\ORM\Locator\LocatorInterface $_locator;

    /**
     * Default LocatorInterface implementation class.
     *
     * @var string
     */
    protected static string $_defaultLocatorClass = MethodLocator::class;

    /**
     * Stores a list of options to be used when instantiating an object
     * with a matching alias.
     *
     * @param string|null $alias Name of the alias
     * @param array|null $options list of options for the alias
     * @return array The config data.
     */
    public static function config(?string $alias = null, ?array $options = null): array
    {
        return static::locator()->config($alias, $options);
    }

    /**
     * Sets and returns a singleton instance of LocatorInterface implementation.
     *
     * @param \CakeDC\OracleDriver\ORM\Locator\LocatorInterface $locator Instance of a locator to use.
     * @return \CakeDC\OracleDriver\ORM\Locator\LocatorInterface
     */
    public static function locator(?LocatorInterface $locator = null): LocatorInterface
    {
        if ($locator instanceof LocatorInterface) {
            static::$_locator = $locator;
        }

        if (!static::$_locator) {
            static::$_locator = new static::$_defaultLocatorClass();
        }

        return static::$_locator;
    }

    /**
     * Get a method instance from the registry.
     *
     * @param string $alias The alias name you want to get.
     * @param array $options The options you want to build the method with.
     * @return \CakeDC\OracleDriver\ORM\Method
     */
    public static function get(string $alias, array $options = []): Method
    {
        return static::locator()->get($alias, $options);
    }

    /**
     * Check to see if an instance exists in the registry.
     *
     * @param string $alias The alias to check for.
     * @return bool
     */
    public static function exists(string $alias): bool
    {
        return static::locator()->exists($alias);
    }

    /**
     * Set an instance.
     *
     * @param string $alias The alias to set.
     * @param \CakeDC\OracleDriver\ORM\Method $object The method to set.
     * @return \CakeDC\OracleDriver\ORM\Method
     */
    public static function set(string $alias, Method $object): Method
    {
        return static::locator()->set($alias, $object);
    }

    /**
     * Removes an instance from the registry.
     *
     * @param string $alias The alias to remove.
     * @return void
     */
    public static function remove(string $alias): void
    {
        static::locator()->remove($alias);
    }

    /**
     * Clears the registry of configuration and instances.
     *
     * @return void
     */
    public static function clear(): void
    {
        static::locator()->clear();
    }

    /**
     * Proxy for static calls on a locator.
     *
     * @param string $name Method name.
     * @param array $arguments Method arguments.
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([static::locator(), $name], $arguments);
    }
}
