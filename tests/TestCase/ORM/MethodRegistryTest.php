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

namespace CakeDC\OracleDriver\Test\TestCase\ORM;

use Cake\TestSuite\TestCase;
use CakeDC\OracleDriver\ORM\Locator\LocatorInterface;
use CakeDC\OracleDriver\ORM\Locator\MethodLocator;
use CakeDC\OracleDriver\ORM\Method;
use CakeDC\OracleDriver\ORM\MethodRegistry;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test case for MethodRegistry
 */
class MethodRegistryTest extends TestCase
{
    /**
     * Original MethodLocator.
     *
     * @var \CakeDC\OracleDriver\ORM\Locator\LocatorInterface
     */
    protected $_originalLocator;

    /**
     * Remember original instance to set it back on tearDown() just to make sure
     * other tests are not broken.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->_originalLocator = MethodRegistry::locator();
    }

    /**
     * tear down
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        MethodRegistry::locator($this->_originalLocator);
    }

    /**
     * Sets and returns mock LocatorInterface instance.
     *
     * @return \CakeDC\OracleDriver\ORM\Locator\LocatorInterface
     */
    protected function _setMockLocator(): MockObject
    {
        $locator = $this->getMockBuilder(LocatorInterface::class)->getMock();
        MethodRegistry::locator($locator);

        return $locator;
    }

    /**
     * Test locator() method.
     *
     * @return void
     */
    public function testLocator(): void
    {
        $this->assertInstanceOf(LocatorInterface::class, MethodRegistry::locator());

        $locator = $this->_setMockLocator();

        $this->assertSame($locator, MethodRegistry::locator());
    }

    /**
     * Test that locator() method is returing MethodLocator by default.
     *
     * @return void
     */
    public function testLocatorDefault(): void
    {
        $locator = MethodRegistry::locator();
        $this->assertInstanceOf(MethodLocator::class, $locator);
    }

    /**
     * Test config() method.
     *
     * @return void
     */
    public function testConfig(): void
    {
        $locator = $this->_setMockLocator();
        $locator->expects($this->once())->method('config')->with('Test', []);

        MethodRegistry::config('Test', []);
    }

    /**
     * Test the get() method.
     *
     * @return void
     */
    public function testGet(): void
    {
        $locator = $this->_setMockLocator();
        $locator->expects($this->once())->method('get')->with('Test', []);

        MethodRegistry::get('Test', []);
    }

    /**
     * Test the get() method.
     *
     * @return void
     */
    public function testSet(): void
    {
        $method = $this->getMockBuilder(Method::class)->getMock();

        $locator = $this->_setMockLocator();
        $locator->expects($this->once())->method('set')->with('Test', $method);

        MethodRegistry::set('Test', $method);
    }

    /**
     * Test the remove() method.
     *
     * @return void
     */
    public function testRemove(): void
    {
        $locator = $this->_setMockLocator();
        $locator->expects($this->once())->method('remove')->with('Test');

        MethodRegistry::remove('Test');
    }

    /**
     * Test the clear() method.
     *
     * @return void
     */
    public function testClear(): void
    {
        $locator = $this->_setMockLocator();
        $locator->expects($this->once())->method('clear');

        MethodRegistry::clear();
    }
}
