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
namespace CakeDC\OracleDriver\TestSuite;

use Cake\TestSuite\TestCase as BaseTestCase;
use CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager;

/**
 * Base test case for the Oracle driver test suite.
 *
 * Loads and unloads the Oracle "method" fixtures (stored procedures) via a
 * shared `OracleFixtureManager` instance, mirroring how CakePHP core loads
 * regular table fixtures from `TestCase::setUp()`.
 */
class TestCase extends BaseTestCase
{
    /**
     * Fixturizes and loads the Oracle method fixtures declared by the test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $manager = OracleFixtureManager::instance();
        $manager->fixturize($this);
        $manager->load($this);
    }

    /**
     * Unloads the Oracle method fixtures declared by the test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        OracleFixtureManager::instance()->unload($this);

        parent::tearDown();
    }
}
