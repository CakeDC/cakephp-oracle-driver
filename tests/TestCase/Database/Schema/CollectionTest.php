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

use Cake\Cache\Cache;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\ConnectionManager;
use CakeDC\OracleDriver\Database\Schema\MethodsCollection;
use CakeDC\OracleDriver\TestSuite\TestCase;

/**
 * Test case for Collection
 */
class CollectionTest extends TestCase
{
    protected bool $autoFixtures = true;

    /**
     * @var array<string>
     */
    public array $codeFixtures = [
        'plugin.CakeDC/OracleDriver.Calc',
    ];

    /**
     * Oracle connection class instance.
     *
     * @var \Cake\Database\Connection
     */
    public $connection;

    /**
     * Setup function
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
        Cache::clear('_cake_method_');
        Cache::enable();
    }

    /**
     * Teardown function
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->connection);
    }

    /**
     * Test that describing non-existent tables fails.
     *
     * Tests for positive describe() calls are in each platformSchema
     * test case.
     *
     * @return void
     */
    public function testDescribeIncorrectMethod(): void
    {
        $this->expectException(DatabaseException::class);
        $schema = new MethodsCollection($this->connection);
        $this->assertNull($schema->describe('CALC.SUM333'));
    }

    /**
     * Tests that schema metadata is cached
     *
     * @return void
     */
    public function testDescribeCache(): void
    {
        $schema = $this->connection->methodSchemaCollection();
        $method = $this->connection->methodSchemaCollection()->describe('CALC.SUM');

        Cache::delete('test_CALC_SUM', '_cake_method_');
        $this->connection->cacheMetadata(true);
        $schema = $this->connection->methodSchemaCollection();

        $result = $schema->describe('CALC.SUM');
        $this->assertEquals($method, $result);

        $result = Cache::read('test_CALC.SUM', '_cake_method_');
        $this->assertEquals($method, $result);
    }
}
