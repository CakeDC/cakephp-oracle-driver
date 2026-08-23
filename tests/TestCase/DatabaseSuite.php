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
use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Applies identifier quoting when a database test suite starts.
 */
final class DatabaseQuotingSubscriber implements StartedSubscriber
{
    /**
     * @inheritDoc
     */
    public function notify(Started $event): void
    {
        $suiteName = $event->testSuite()->name();
        if (!in_array($suiteName, ['Database', 'ORM', 'default'], true)) {
            return;
        }

        $quoting = getenv('ORACLE_IDENTIFIER_QUOTING');
        $enabled = $quoting === false || $quoting === '1';
        DatabaseSuite::applyIdentifierQuoting($enabled);
    }
}

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
        $facade->registerSubscriber(new DatabaseQuotingSubscriber());
    }
}
