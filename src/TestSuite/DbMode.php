<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\TestSuite;

use Cake\Datasource\ConnectionManager;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

class DbMode implements TestListener
{
    /**
     * Holds a reference to the container test suite
     *
     * @var \PHPUnit\Framework\TestSuite
     */
    protected $_first;

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
        
        ConnectionManager::get('test')->getDriver()->enableAutoQuoting(true);
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
     * @param \Throwable $e Skipped test exception
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
        ConnectionManager::get('test')->getDriver()->enableAutoQuoting(true);
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
