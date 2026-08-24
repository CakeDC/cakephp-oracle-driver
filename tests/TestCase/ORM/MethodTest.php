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

use CakeDC\OracleDriver\Database\Driver\OraclePDO;
use CakeDC\OracleDriver\ORM\MethodRegistry;
use CakeDC\OracleDriver\TestSuite\TestCase;

/**
 * Tests Method class
 */
class MethodTest extends TestCase
{
    public $codeFixtures = ['plugin.CakeDC/OracleDriver.Calc'];

    /**
     * Method call test
     *
     * @return void
     */
    public function testMethodCall(): void
    {
        $method = MethodRegistry::get('CalcSum', ['method' => 'CALC.SUM']);

        $this->skipIf(
            $method->getConnection()->getDriver() instanceof OraclePDO,
            'OraclePDO does not support the requirements of this test.',
        );

        $request = $method->newRequest(['A' => 5, 'B' => 10]);
        $this->assertTrue($request->isNew());
        $this->assertTrue($method->execute($request));
        $this->assertFalse($request->isNew());
        $this->assertEquals($request[':result'], 15);
        $this->assertEquals($request->result(), 15);
    }

    /**
     * Output parameter method call test
     *
     * @return void
     */
    public function testOutParameterMethodCall(): void
    {
        $method = MethodRegistry::get('CalcTwice', ['method' => 'CALC.TWICE']);

        $this->skipIf(
            $method->getConnection()->getDriver() instanceof OraclePDO,
            'OraclePDO does not support the requirements of this test.',
        );

        $request = $method->newRequest(['A' => 5]);
        $this->assertTrue($request->isNew());
        $this->assertTrue($method->execute($request));
        $this->assertFalse($request->isNew());

        $this->assertEquals($request->get('B'), 10);
    }
}
