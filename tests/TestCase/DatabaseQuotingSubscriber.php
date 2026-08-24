<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase;

use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;

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
        FixtureShutdownSubscriber::markStarted();

        $quoting = getenv('ORACLE_IDENTIFIER_QUOTING');
        $enabled = $quoting === false || $quoting === '1';
        DatabaseSuite::applyIdentifierQuoting($enabled);
    }
}
