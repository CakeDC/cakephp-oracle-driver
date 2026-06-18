<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase\ORM;

use Cake\Test\TestCase\ORM\Query\CompositeKeysTest as CakeCompositeKeysTest;
use CakeDC\OracleDriver\Database\Driver\OracleBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for table operations involving composite keys
 */
class CompositeKeysTest extends CakeCompositeKeysTest
{
    /**
     * Test that saving into composite primary keys where one column is missing & autoIncrement works.
     *
     * SQLite is skipped because it doesn't support autoincrement composite keys.
     */
    #[Group('save')]
    public function testSaveNewCompositeKeyIncrement(): void
    {
        $this->markTestSkipped();
    }

    /**
     * Tests that HasMany associations are correctly eager loaded and results
     * correctly nested when multiple foreignKeys are used
     */
    #[DataProvider('strategiesProviderHasMany')]
    public function testHasManyEager(string $strategy): void
    {
        $this->markTestSkipped();
    }

    /**
     * Tests that BelongsToMany associations are correctly eager loaded when multiple
     * foreignKeys are used
     */
    #[DataProvider('strategiesProviderBelongsToMany')]
    public function testBelongsToManyEager(string $strategy): void
    {
        $this->markTestSkipped();
    }

    /**
     * Helper method to skip tests when connection is Oracle.
     *
     * @return void
     */
    public function skipIfOracle(): void
    {
        $this->skipIf(
            $this->connection->getDriver() instanceof OracleBase,
            'Oracle does not support the requirements of this test or test not ready yet.'
        );
    }
}
