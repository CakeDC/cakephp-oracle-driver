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

use Cake\TestSuite\Fixture\TruncateStrategy;

/**
 * Truncates fixture tables before and after each test for Oracle.
 */
class OracleTruncateStrategy extends TruncateStrategy
{
    /**
     * @inheritDoc
     */
    public function setupTest(array $fixtureNames): void
    {
        if ($fixtureNames === []) {
            return;
        }

        $fixtures = $this->helper->loadFixtures($fixtureNames);
        $this->helper->truncate($fixtures);
        $this->helper->insert($fixtures);

        $this->fixtures = $fixtures;
    }

    /**
     * @inheritDoc
     */
    public function teardownTest(): void
    {
        if (!$this->fixtures) {
            return;
        }

        $this->helper->truncate($this->fixtures);
    }
}
