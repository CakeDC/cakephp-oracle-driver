<?php
/**
 * Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\OracleDriver\ORM;

use CakeDC\OracleDriver\Database\Schema\Method as MethodSchema;
use CakeDC\OracleDriver\ORM\Method\ResultSet;
use Cake\Database\Driver;
use Cake\Database\TypeConverterTrait;
use Cake\Utility\Inflector;
use InvalidArgumentException;

/**
 * An request represents a single method call from a repository.
 */
trait RequestTrait
{
    use TypeConverterTrait;

    /**
     * Holds a cached list of methods that exist in the instanced class
     *
     * @var array
     */
    protected static $_accessors = [];

    /**
     * Holds all properties and their values for this request
     *
     * @var array
     */
    protected $_properties = [];

    /**
     * Holds all properties and their values for this request after conversion from PHP to database format.
     *
     * @var array
     */
    protected $_castedProperties = [];

    /**
     * Holds the name of the class for the instance object
     *
     * @var string
     */
    protected $_className;

    /**
     * Holds the repository of the method class for the instance object
     *
     * @var Method
     */
    protected $_repository;

    /**
     * Holds the driver for type casting
     *
     * @var Driver
     */
    protected $_driver;

    /**
     * Indicates whether or not this request is yet to be called.
     * Requests default to assuming they are new.
     *
     * @var bool
     */
    protected $_new = true;

    /**
     * Magic getter to access properties that have been set in this request
     *
     * @param string $property Name of the property to access
     * @return mixed
     */
    public function &__get($property)
    {
        return $this->get($property);
    }

    /**
     * Magic setter to add or edit a property in this request
     *
     * @param string $property The name of the property to set
     * @param mixed $value The value to set to the property
     * @return void
     */
    public function __set($property, $value)
    {
        $this->set($property, $value);
    }

    /**
     * Returns the value of a property by name
     *
     * @param string $property the name of the property to retrieve
     * @return mixed
     * @throws \InvalidArgumentException if an empty property name is passed
     */
    public function &get($property)
    {
        if (!strlen((string)$property)) {
            throw new InvalidArgumentException('Cannot get an empty property');
        }

        $value = null;
        $method = '_get' . Inflector::camelize($property);

        if (isset($this->_properties[$property])) {
            $value =& $this->_properties[$property];
        }

        if ($this->_methodExists($method)) {
            $result = $this->{$method}($value);
            return $result;
        }
        return $value;
    }

    /**
     * Determines whether a method exists in this class
     *
     * @param string $method the method to check for existence
     * @return bool true if method exists
     */
    protected function _methodExists($method)
    {
        if (empty(static::$_accessors[$this->_className])) {
            static::$_accessors[$this->_className] = array_flip(get_class_methods($this));
        }
        return isset(static::$_accessors[$this->_className][$method]);
    }

    /**
     * Sets a single property inside this request.
     *
     * ### Example:
     *
     * ```
     * $request->set('name', 'Andrew');
     * ```
     *
     * It is also possible to mass-assign multiple properties to this request
     * with one call by passing a hashed array as properties in the form of
     * property => value pairs
     *
     * ### Example:
     *
     * ```
     * $request->set(['name' => 'andrew', 'id' => 1]);
     * echo $request->name // prints andrew
     * echo $request->id // prints 1
     * ```
     *
     * Some times it is handy to bypass setter functions in this request when assigning
     * properties. You can achieve this by disabling the `setter` option using the
     * `$options` parameter:
     *
     * ```
     * $request->set('name', 'Andrew', ['setter' => false]);
     * $request->set(['name' => 'Andrew', 'id' => 1], ['setter' => false]);
     * ```
     *
     * @param string|array $property the name of property to set or a list of
     * properties with their respective values
     * @param mixed $value The value to set to the property or an array if the
     * first argument is also an array, in which case will be treated as $options
     * @param array $options options to be used for setting the property. Allowed option
     * keys are `setter`
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function set($property, $value = null, array $options = [])
    {
        $isString = is_string($property);
        if ($isString && $property !== '') {
            $property = [$property => $value];
        } else {
            $options = (array)$value;
        }

        if (!is_array($property)) {
            throw new InvalidArgumentException('Cannot set an empty property');
        }
        $options += ['setter' => true];

        foreach ($property as $p => $value) {
            if (!$options['setter']) {
                $this->_properties[$p] = $value;
                continue;
            }

            $setter = '_set' . Inflector::camelize($p);
            if ($this->_methodExists($setter)) {
                $value = $this->{$setter}($value);
            }
            $this->_properties[$p] = $value;
        }

        return $this;
    }

    /**
     * Returns whether this request contains a property named $property
     * regardless of if it is empty.
     *
     * @param string $property The property to check.
     * @return bool
     * @see \Cake\ORM\Request::has()
     */
    public function __isset($property)
    {
        return $this->has($property);
    }

    /**
     * Returns whether this request contains a property named $property
     * regardless of if it is empty.
     *
     * ### Example:
     *
     * ```
     * $request = new Request(['id' => 1, 'name' => null]);
     * $request->has('id'); // true
     * $request->has('name'); // false
     * $request->has('last_name'); // false
     * ```
     *
     * When checking multiple properties. All properties must not be null
     * in order for true to be returned.
     *
     * @param string|array $property The property or properties to check.
     * @return bool
     */
    public function has($property)
    {
        foreach ((array)$property as $prop) {
            if ($this->get($prop) === null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removes a property from this request
     *
     * @param string $property The property to unset
     * @return void
     */
    public function __unset($property)
    {
        $this->unsetProperty($property);
    }

    /**
     * Removes a property or list of properties from this request
     *
     * ### Examples:
     *
     * ```
     * $request->unsetProperty('name');
     * $request->unsetProperty(['name', 'last_name']);
     * ```
     *
     * @param string|array $property The property to unset.
     * @return $this
     */
    public function unsetProperty($property)
    {
        $property = (array)$property;
        foreach ($property as $p) {
            unset($this->_properties[$p]);
        }

        return $this;
    }

    /**
     * Returns the properties that will be serialized as JSON
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Returns an array with all the properties that have been set
     * to this request
     *
     * This method will recursively transform entities assigned to properties
     * into arrays as well.
     *
     * @return array
     */
    public function toArray()
    {
        $result = [];
        foreach ($this->visibleProperties() as $property) {
            $value = $this->get($property);
            if (is_array($value)) {
                $result[$property] = [];
                foreach ($value as $k => $object) {
                    $result[$property][$k] = $object;
                }
            } else {
                $result[$property] = $value;
            }
        }
        return $result;
    }

    /**
     * Get the list of visible properties.
     *
     * The list of visible properties is all standard properties
     * plus virtual properties minus hidden properties.
     *
     * @return array A list of properties that are 'visible' in all
     *     representations.
     */
    public function visibleProperties()
    {
        $properties = array_keys($this->_properties);
        return $properties;
    }

    /**
     * Implements isset($request);
     *
     * @param mixed $offset The offset to check.
     * @return bool Success
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Implements $request[$offset];
     *
     * @param mixed $offset The offset to get.
     * @return mixed
     */
    public function &offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Implements $request[$offset] = $value;
     *
     * @param mixed $offset The offset to set.
     * @param mixed $value The value to set.
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * Implements unset($result[$offset);
     *
     * @param mixed $offset The offset to remove.
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->unsetProperty($offset);
    }

    /**
     * Binds all the stored values in this object to the passed statement.
     *
     * @param \Cake\Database\StatementInterface $statement The statement to add parameters to.
     * @return void
     */
    public function attachTo($statement)
    {
        $properties = $this->_properties;
        if (empty($properties)) {
            return;
        }
        foreach ($properties as $name => $value) {
            $parameter = $this->_repository->schema()
                                           ->parameter($name);
            if ($parameter === null) {
                continue;
            }
            if ($name === ':result') {
                $paramName = $name;
            } else {
                $paramName = ':' . $name;
            }
            if ($parameter !== null) {
                $type = $parameter['type'];
                list($value, $type) = $this->cast($this->_properties[$name], $type);
                $this->_castedProperties[$name] = $value;
                if ($parameter['in']) {
                    if ($parameter['out']) {
                        $this->_properties[$name] = $value;
                    }
                }
                if ($parameter['out']) {
                    $statement->bindParam($paramName, $this->_properties[$name], $type, $parameter['length']);
                } else {
                    $statement->bindParam($paramName, $this->_castedProperties[$name], $type);
                }
            }
        }
    }

    /**
     * Perform cursor fetching.
     *
     * @param string $name Property name.
     * @param array $options Cursor ResultSet configuration options.
     * @return ResultSet
     */
    public function fetchCursor($name, $options = [])
    {
        $options += ['entityClass' => 'Cake\ORM\Entity'];
        if ($this->isNew()) {
            throw new InvalidArgumentException('Cannot fetch cursor on not executed request');
        }
        $parameter = $this->_repository->schema()->parameter($name);
        if (empty($parameter)) {
            throw new InvalidArgumentException('Cannot fetch cursor for not declared parameter');
        }
        if ($parameter['type'] !== 'cursor') {
            throw new InvalidArgumentException('Cannot fetch cursor for parameter that have wrong type');
        }
        $property = $this->get($name);
        $statement = $this->_repository->connection()->prepareMethod($property);
        $statement->queryString = __('fetch {0} cursor', $name);
        $statement->execute();
        return new ResultSet($this->_repository, $statement, $options);
    }

    /**
     * Returns whether or not this request has already been called.
     * This method can return null in the case there is no prior information on
     * the status of this request.
     *
     * If called with a boolean it will set the known status of this request,
     * true means that the it is not yet called, false that it already is.
     *
     * @param bool|null $new true if it is known this request was called
     * @return bool Whether or not the request has been called.
     */
    public function isNew($new = null)
    {
        if ($new === null) {
            return $this->_new;
        }

        $new = (bool)$new;
        return $this->_new = $new;
    }

    /**
     * Apply schema structure to the request object.
     *
     * @param MethodSchema $schema Method schema object instance.
     * @return void
     */
    public function applySchema(MethodSchema $schema)
    {
        $parameters = $schema->parameters();
        foreach ($parameters as $name) {
            $parameter = $schema->parameter($name);
            if ($parameter['in']) {
                $this->set($name, null); // @todo default ???
            }
            if ($parameter['out']) {
                $this->set($name, null);
            }
        }
    }

    /**
     * Helper method to get function result. Should be used only in case function was called.
     * For procedures always will return null.
     *
     * @return mixed
     */
    public function result()
    {
        if ($this->isNew() || !$this->_repository->schema()->isFunction()) {
            return null;
        }
        return $this->_properties[':result'];
    }

    /**
     * Returns a string representation of this object in a human readable format.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }

    /**
     * Returns an array that can be used to describe the internal state of this
     * object.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return $this->_properties + [
            '[new]' => $this->isNew(),
        ];
    }
}
