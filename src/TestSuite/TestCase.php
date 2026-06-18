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

use Cake\TestSuite\TestCase as CakeTestCase;
use CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager;
use Exception;

/**
 * CakeDC Oracle TestCase class
 */
abstract class TestCase extends CakeTestCase
{
    /**
     * The class responsible for managing PL/SQL code fixture lifecycle.
     *
     * @var \CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager|null
     */
    public ?OracleFixtureManager $methodFixtureManager = null;

  /**
     * Shared Oracle code fixture manager instance.
     *
     * @var \CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager|null
     */
    protected static ?OracleFixtureManager $oracleFixtureManager = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (property_exists($this, 'codeFixtures') && !empty($this->codeFixtures)) {
            if (self::$oracleFixtureManager === null) {
                self::$oracleFixtureManager = new OracleFixtureManager();
            }
            $this->methodFixtureManager = self::$oracleFixtureManager;
            self::$oracleFixtureManager->fixturize($this);
            self::$oracleFixtureManager->load($this);
        }
    }

    /**
     * Chooses which code fixtures to load for a given test.
     *
     * Each parameter is a code module name that corresponds to a fixture.
     *
     * @return void
     * @throws \Exception when no fixture manager is available.
     */
    public function loadMethodFixtures()
    {
        if (empty($this->methodFixtureManager)) {
            throw new Exception('No fixture manager to load the test fixture');
        }
        $args = func_get_args();
        foreach ($args as $class) {
            $this->methodFixtureManager->loadSingleMethod($class, null, $this->dropTables);
        }
    }
}
