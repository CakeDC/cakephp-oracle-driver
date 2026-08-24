<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase\Database\Schema;

use Cake\Database\Driver;
use Cake\Database\Type\BaseType;

/**
 * Mock class for testing baseType inheritance
 */
class FooType extends BaseType
{
    /**
     * @inheritDoc
     */
    public function getBaseType(): ?string
    {
        return 'integer';
    }

    /**
     * @inheritDoc
     */
    public function toDatabase(mixed $value, Driver $driver): mixed
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toPHP(mixed $value, Driver $driver): mixed
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function marshal(mixed $value): mixed
    {
        return $value;
    }
}
