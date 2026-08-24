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
namespace CakeDC\OracleDriver\Test\TestCase;

use Cake\Datasource\ConnectionManager;
use CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Applies Oracle identifier-quoting permutations for database tests.
 */
class DatabaseSuite implements Extension
{
    /**
     * Identifier-quoting permutations executed by the test runner.
     *
     * @var array<string, bool>
     */
    public const PERMUTATIONS = [
        'Identifier Quoting' => true,
        'No identifier quoting' => false,
    ];

    /**
     * Configures identifier quoting on the test connection.
     *
     * @param bool $enabled Whether auto-quoting should be enabled.
     * @return void
     */
    public static function applyIdentifierQuoting(bool $enabled): void
    {
        ConnectionManager::get('test')->getDriver()->enableAutoQuoting($enabled);
    }

    /**
     * @inheritDoc
     */
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        if (isset($_SERVER['argv'])) {
            OracleFixtureManager::instance()->setDebug(
                in_array('--debug', $_SERVER['argv'], true),
            );
        }

        $facade->registerSubscriber(new DatabaseQuotingSubscriber());
        $facade->registerSubscriber(new FixtureShutdownSubscriber());
    }
}
