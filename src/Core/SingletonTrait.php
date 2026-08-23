<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Core;

/**
 * Singleton trait.
 */
trait SingletonTrait
{
    /**
     * Object instance.
     *
     * @var mixed
     */
    protected static $_instance;

    /**
     * Returns object instance.
     *
     * @return object instance.
     */
    final public static function getInstance()
    {
        return static::$_instance ?? static::$_instance = new static();
    }

    /**
     * Singleton constructor.
     */
    final private function __construct()
    {
        $this->init();
    }

    /**
     * Default initialization instance.
     *
     * @return void
     */
    protected function init()
    {
    }

    /**
     * Default wakeup behavior.
     *
     * @return void
     */
    public function __wakeup()
    {
    }

    /**
     * Default clone behavior.
     */
    private function __clone()
    {
    }
}
