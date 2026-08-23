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

namespace CakeDC\OracleDriver\Test\TestCase\Database\Schema;

use CakeDC\OracleDriver\Database\Schema\MethodSchema;
use CakeDC\OracleDriver\ORM\MethodRegistry;
use CakeDC\OracleDriver\TestSuite\TestCase;

/**
 * Test case for Method
 */
class MethodTest extends TestCase
{
    protected bool $autoFixtures = true;

    /**
     * @var array<string>
     */
    public array $codeFixtures = [
        'plugin.CakeDC/OracleDriver.Calc',
    ];

    protected function tearDown(): void
    {
        MethodRegistry::clear();
        parent::tearDown();
    }

    /**
     * Test construction with parameters
     *
     * @return void
     */
    public function testConstructWithParameters(): void
    {
        $parameters = [
            'a' => [
                'type' => 'float',
                'in' => true,
            ],
            'b' => [
                'type' => 'float',
                'in' => true,
            ],
        ];
        $method = new MethodSchema('CALC.SUM', $parameters);
        $this->assertEquals(['a', 'b'], $method->parameters());
    }
}
