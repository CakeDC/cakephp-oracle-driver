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

namespace CakeDC\OracleDriver\Database\Schema;

use Cake\Database\Exception;
use Cake\Database\Type;

/**
 * Represents a single method in a database schema.
 *
 * Can either be populated using the reflection API's
 * or by incrementally building an instance using
 * methods.
 *
 * Once created Table instances can be added to
 * Schema\MethodsCollection objects.
 */
class Method
{

    /**
     * The name of the method
     *
     * @var string
     */
    protected $_method;

    /**
     * The type of method
     *
     * @var bool
     */
    protected $_isFunction;

    /**
     * Parameters in the method.
     *
     * @var array
     */
    protected $_parameters = [];

    /**
     * A map with columns to types
     *
     * @var array
     */
    protected $_typeMap = [];

    /**
     * The valid keys that can be used in a IN column
     * definition.
     *
     * @var array
     */
    protected static $_columnInParameters = [
        'type' => null,
        'in' => null,
        'out' => null,
    ];

    /**
     * The valid keys that can be used in a OUT column
     * definition.
     *
     * @var array
     */
    protected static $_columnOutParameters = [
        'type' => null,
        'in' => null,
        'out' => null,
        'length' => null,
    ];
    
    /**
     * Additional type specific properties.
     *
     * @var array
     */
    protected static $_columnExtras = [
    ];

    /**
     * Constructor.
     *
     * @param string $method The method name.
     * @param array $parameters The list of columns for the schema.
     */
    public function __construct($method, array $parameters = [])
    {
        $this->_method = $method;
        foreach ($parameters as $parameter => $definition) {
            $this->addParameter($parameter, $definition);
        }
    }

    /**
     * Get the name of the method.
     *
     * @return string
     */
    public function name()
    {
        return $this->_method;
    }

    /**
     * Add a column to the table.
     *
     * ### Attributes
     *
     * Parameters can have several attributes:
     *
     * - `type` The type of the column. This should be
     *   one of CakePHP's abstract types.
     * - `in` The parameter is IN.
     * - `out` The parameter is OUT.
     *
     * @param string $name The name of the column
     * @param array $attrs The attributes for the column.
     * @return $this
     */
    public function addParameter($name, $attrs)
    {
        $attrs += ['function' => null];
        if (is_string($attrs)) {
            $attrs = ['type' => $attrs];
        }
        if ($attrs['out']) {
            $valid = static::$_columnOutParameters;
        } else {
            $valid = static::$_columnInParameters;
        }
        if (isset(static::$_columnExtras[$attrs['type']])) {
            $valid += static::$_columnExtras[$attrs['type']];
        }
        if ($attrs['function'] === true) {
            $this->_isFunction = true;
        }
        $attrs = array_intersect_key($attrs, $valid);
        $this->_parameters[$name] = $attrs + $valid;
        $this->_typeMap[$name] = $this->_parameters[$name]['type'];
        return $this;
    }

    /**
     * Get the parameter names in the method.
     *
     * @return array
     */
    public function parameters()
    {
        return array_keys($this->_parameters);
    }

    /**
     * Get parameter data for the method.
     *
     * @param string $name The parameter name.
     * @return array|null Parameter data or null.
     */
    public function parameter($name)
    {
        if (!isset($this->_parameters[$name])) {
            return null;
        }
        $parameter = $this->_parameters[$name];
        return $parameter;
    }

    /**
     * Sets the type of a parameter, or returns its current type
     * if none is passed.
     *
     * @param string $name The column to get the type of.
     * @param string $type The type to set the column to.
     * @return string|null Either the column type or null.
     */
    public function parameterType($name, $type = null)
    {
        if (!isset($this->_parameters[$name])) {
            return null;
        }
        if ($type !== null) {
            $this->_parameters[$name]['type'] = $type;
            $this->_typeMap[$name] = $type;
        }
        return $this->_parameters[$name]['type'];
    }

    /**
     * Sets the type of a parameter, or returns its current type
     * if none is passed.
     *
     * @param string $name The column to get the type of.
     * @param string $direction The direction to set the parameter to.
     * @return string|null Either the parameter direction or null.
     */
    public function parameterDirection($name, $direction = null)
    {
        if (!isset($this->_parameters[$name])) {
            return null;
        }
        if ($direction !== null) {
            if (strpos($direction, 'IN') !== false) {
                $this->_parameters[$name]['in'] = true;
            } else {
                $this->_parameters[$name]['in'] = false;
            }
            if (strpos($direction, 'OUT') !== false) {
                $this->_parameters[$name]['out'] = true;
            } else {
                $this->_parameters[$name]['out'] = false;
            }
        }
        $result = null;
        if ($this->_parameters[$name]['in']) {
            $result = 'IN';
        }
        if ($this->_parameters[$name]['in']) {
            if ($result !== null) {
                $result .= '/';
            }
            $result .= 'OUT';
        }
        return $result;
    }

    /**
     * Returns an array where the keys are the column names in the schema
     * and the values the database type they have.
     *
     * @return array
     */
    public function typeMap()
    {
        return $this->_typeMap;
    }


    /**
     * Get the method type.
     *
     * @return array
     */
    public function isFunction()
    {
        return $this->_isFunction;
    }

}
