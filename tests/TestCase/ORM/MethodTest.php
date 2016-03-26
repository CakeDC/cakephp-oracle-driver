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

namespace CakeDC\OracleDriver\Test\TestCase\ORM;

use CakeDC\OracleDriver\ORM\Method;
use CakeDC\OracleDriver\ORM\MethodRegistry;
use CakeDC\OracleDriver\TestSuite\TestCase;


/**
 * Tests Method class
 *
 */
class MethodTest extends TestCase
{

    public $codeFixtures = ['plugin.CakeDC/OracleDriver.Calc'];
	
    public function testMethodCall() {
        $method = MethodRegistry::get('CalcSum', ['method' => 'CALC.SUM']);
        $request = $method->newRequest(['A' => 5, 'B' => 10]);
        $this->assertTrue($request->isNew());
        $this->assertTrue($method->execute($request));
        $this->assertFalse($request->isNew());
        $this->assertEquals($request[':result'], 15);
        $this->assertEquals($request->result(), 15);
    }

    public function testOutParameterMethodCall() {
        $method = MethodRegistry::get('CalcTwice', ['method' => 'CALC.TWICE']);
        $request = $method->newRequest(['A' => 5, 'B' => null]);
        $this->assertTrue($request->isNew());
        $this->assertTrue($method->execute($request));
        $this->assertFalse($request->isNew());

        $this->assertEquals($request->get('B'), 10);
        $this->assertEquals($request[':result'], 'OK');
        $this->assertEquals($request->result(), 'OK');
    }
}
