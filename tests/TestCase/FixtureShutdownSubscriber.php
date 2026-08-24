<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase;

use CakeDC\OracleDriver\TestSuite\Fixture\OracleFixtureManager;
use PHPUnit\Event\TestSuite\Finished;
use PHPUnit\Event\TestSuite\FinishedSubscriber;

/**
 * Tracks the nesting depth of started/finished test suites so the shared
 * fixture manager can be torn down exactly once, after the outermost suite
 * finishes.
 */
final class FixtureShutdownSubscriber implements FinishedSubscriber
{
    private static int $depth = 0;

    /**
     * Records that a test suite has started.
     *
     * @return void
     */
    public static function markStarted(): void
    {
        self::$depth++;
    }

    /**
     * @inheritDoc
     */
    public function notify(Finished $event): void
    {
        self::$depth--;
        if (self::$depth <= 0) {
            OracleFixtureManager::instance()->shutDown();
        }
    }
}
