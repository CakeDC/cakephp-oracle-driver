<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase\ORM;

use CakeDC\OracleDriver\ORM\Request;

/**
 * Request subclass with pre-defined accessor stubs for PHPUnit onlyMethods() compatibility.
 *
 * PHPUnit 12 removes addMethods() which allowed mocking non-existent methods.
 * Defining the stubs here lets tests use onlyMethods() instead.
 */
class RequestWithAccessors extends Request
{
    protected function _setName(mixed $value): mixed
    {
        return $value;
    }

    protected function _getName(mixed $value): mixed
    {
        return $value;
    }

    protected function _setStuff(mixed $value): mixed
    {
        return $value;
    }

    protected function _getThings(mixed $value): mixed
    {
        return $value;
    }

    protected function _setFoo(mixed $value): mixed
    {
        return $value;
    }

    protected function _getBar(mixed $value): mixed
    {
        return $value;
    }

    protected function _setBar(mixed $value): mixed
    {
        return $value;
    }

    protected function _getVeryLongProperty(mixed $value): mixed
    {
        return $value;
    }

    protected function _setVeryLongProperty(mixed $value): mixed
    {
        return $value;
    }

    public function clean(): void
    {
    }
}
