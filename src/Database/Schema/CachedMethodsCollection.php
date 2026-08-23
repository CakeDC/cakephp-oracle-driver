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
namespace CakeDC\OracleDriver\Database\Schema;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionInterface;

/**
 * Extends the schema collection class to provide caching
 *
 */
class CachedMethodsCollection extends MethodsCollection
{
    /**
     * The name of the cache config key to use for caching method metadata,
     * of false if disabled.
     *
     * @var string|bool
     */
    protected $_cache = false;

    /**
     * Constructor.
     *
     * @param \Cake\Datasource\ConnectionInterface $connection The connection instance.
     * @param string|bool $cacheKey The cache key or boolean false to disable caching.
     */
    public function __construct(ConnectionInterface $connection, $cacheKey = true)
    {
        parent::__construct($connection);
        $this->cacheMetadata($cacheKey);
    }

    /**
     * {@inheritDoc}
     *
     */
    public function describe(string $name, array $options = []): \CakeDC\OracleDriver\Database\Schema\MethodSchema
    {
        $options += ['forceRefresh' => false];
        $cacheConfig = $this->cacheMetadata();
        $cacheKey = $this->cacheKey($name);

        if (!empty($cacheConfig) && !$options['forceRefresh']) {
            $cached = Cache::read($cacheKey, $cacheConfig);
            if ($cached !== null) {
                return $cached;
            }
        }

        $method = parent::describe($name, $options);

        if (!empty($cacheConfig)) {
            Cache::write($cacheKey, $method, $cacheConfig);
        }

        return $method;
    }

    /**
     * Get the cache key for a given name.
     *
     * @param string $name The name to get a cache key for.
     * @return string The cache key.
     */
    public function cacheKey($name): string
    {
        return $this->_connection->configName() . '_' . $name;
    }

    /**
     * Sets the cache config name to use for caching method metadata, or
     * disables it if false is passed.
     * If called with no arguments it returns the current configuration name.
     *
     * @param bool $enable whether or not to enable caching
     * @return string|bool
     */
    public function cacheMetadata($enable = null)
    {
        if ($enable === null) {
            return $this->_cache;
        }
        
        if ($enable) {
            $enable = '_cake_method_';
        }

        return $this->_cache = $enable;
    }
}
