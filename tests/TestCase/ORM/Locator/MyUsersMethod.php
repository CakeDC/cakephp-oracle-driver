<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase\ORM\Locator;

use CakeDC\OracleDriver\ORM\Method;

/**
 * Used to test correct class is instantiated when using $this->_locator->get();
 */
class MyUsersMethod extends Method
{
    /**
     * Overrides default method name
     *
     * @var string|null
     */
    protected ?string $_method = 'users';
}
