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
namespace CakeDC\OracleDriver\TestSuite\Fixture;

use Cake\TestSuite\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

/**
 * Test listener used to inject a fixture manager in all tests that
 * are composed inside a Test Suite
 */
class OracleFixtureInjector implements TestListener
{
    /**
     * The instance of the fixture manager to use
     *
     * @var \CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager
     */
    protected $_fixtureManager;

    /**
     * Holds a reference to the container test suite
     *
     * @var \PHPUnit\Framework\TestSuite
     */
    protected $_first;

    /**
     * Constructor. Save internally the reference to the passed fixture manager
     *
     * @param \CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager $manager The fixture manager
     */
    public function __construct(OracleFixtureManager $manager)
    {
        if (isset($_SERVER['argv'])) {
            $manager->setDebug(in_array('--debug', $_SERVER['argv']));
        }
        $this->_fixtureManager = $manager;
        $this->_fixtureManager->shutDown();
    }

    /**
     * Iterates the tests inside a test suite and creates the required fixtures as
     * they were expressed inside each test case.
     *
     * @param \PHPUnit\Framework\TestSuite $suite The test suite
     * @return void
     */
    public function startTestSuite(TestSuite $suite): void
    {
        if (empty($this->_first)) {
            $this->_first = $suite;
        }
    }

    /**
     * Destroys the fixtures created by the fixture manager at the end of the test
     * suite run
     *
     * @param \PHPUnit\Framework\TestSuite $suite The test suite
     * @return void
     */
    public function endTestSuite(TestSuite $suite): void
    {
        if ($this->_first === $suite) {
            $this->_fixtureManager->shutDown();
        }
    }

    /**
     * Not Implemented
     *
     * @param \Test $test The test to add errors from.
     * @param \Throwable $e The exception
     * @param float $time current time
     * @return void
     */
    public function addError(Test $test, \Throwable $e, $time): void
    {
    }

    /**
     * Not Implemented
     *
     * @param \PHPUnit\Framework\Test $test The test to add warnings from.
     * @param \PHPUnit\Framework\Warning $e The warning
     * @param float $time current time
     * @return void
     */
    public function addWarning(Test $test, Warning $e, $time): void
    {
    }

    /**
     * Not Implemented
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @param \PHPUnit\Framework\AssertionFailedError $e The failed assertion
     * @param float $time current time
     * @return void
     */
    public function addFailure(Test $test, AssertionFailedError $e, $time): void
    {
    }

    /**
     * Not Implemented
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @param \Throwable $e The incomplete test error.
     * @param float $time current time
     * @return void
     */
    public function addIncompleteTest(Test $test, \Throwable $e, $time): void
    {
    }

    /**
     * Not Implemented
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @param \Exception $e Skipped test exception
     * @param float $time current time
     * @return void
     */
    public function addSkippedTest(Test $test, \Throwable $e, $time): void
    {
    }

    /**
     * Adds fixtures to a test case when it starts.
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @return void
     */
    public function startTest(Test $test): void
    {
        if ($test instanceof TestCase) {
            $this->_fixtureManager->fixturize($test);
            $this->_fixtureManager->load($test);
        }
    }

    /**
     * Unloads fixtures from the test case.
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @param float $time current time
     * @return void
     */
    public function endTest(Test $test, $time): void
    {
        if ($test instanceof TestCase) {
            $this->_fixtureManager->unload($test);
        }
    }

    /**
     * Not Implemented
     *
     * @param \PHPUnit\Framework\Test $test The test case
     * @param \Throwable $e The exception to track
     * @param float $time current time
     * @return void
     */
    public function addRiskyTest(Test $test, \Throwable $e, $time): void
    {
    }
}
