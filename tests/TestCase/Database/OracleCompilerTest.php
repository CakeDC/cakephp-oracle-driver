<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase\Database;

use Cake\Database\DriverFeatureEnum;
use Cake\Database\Expression\CommonTableExpression;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Schema\TableSchema;
use Cake\Database\ValueBinder;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use CakeDC\OracleDriver\Database\Driver\OracleBase;
use CakeDC\OracleDriver\Database\Driver\OraclePDO;
use CakeDC\OracleDriver\Database\Oracle12Compiler;
use CakeDC\OracleDriver\Database\OracleCompiler;
use CakeDC\OracleDriver\Database\Schema\OracleSchema;
use Mockery;
use ReflectionProperty;

/**
 * Regression tests for the Oracle driver gap closures (docs/working-memory/gap-analysis.md):
 * compiler SELECT parts, MINUS set operation, GROUP BY strictness rescue,
 * LISTAGG translation, supports() matrix and schema type/default handling.
 *
 * SQL generation only; no tables or live data required beyond the test connection.
 */
class OracleCompilerTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * Skip when the test connection is not Oracle (e.g. local sqlite runs).
     *
     * @return \Cake\Database\Connection
     */
    protected function _oracleConnection()
    {
        $connection = ConnectionManager::get('test');
        $this->skipIf(
            !($connection->getDriver() instanceof OracleBase),
            'Requires the Oracle test connection.',
        );

        return $connection;
    }

    /**
     * EXCEPT must compile to Oracle's MINUS keyword.
     *
     * @return void
     */
    public function testExceptCompilesToMinus(): void
    {
        $connection = $this->_oracleConnection();
        $other = $connection->selectQuery()->select(['id'])->from('authors');
        $query = $connection->selectQuery()->select(['id'])->from('articles')->except($other);

        $sql = $query->sql();
        $this->assertStringContainsString('MINUS', $sql);
        $this->assertStringNotContainsString('EXCEPT', $sql);
    }

    /**
     * WITH clause must be emitted, without the RECURSIVE keyword Oracle lacks.
     *
     * @return void
     */
    public function testWithClauseWithoutRecursive(): void
    {
        $connection = $this->_oracleConnection();
        $cteQuery = $connection->selectQuery()->select(['id'])->from('articles');
        $cte = new CommonTableExpression('recent', $cteQuery);
        $query = $connection->selectQuery()->select(['id'])->from('recent')->with($cte);

        $sql = $query->sql();
        $this->assertStringContainsString('WITH', $sql);
        $this->assertStringNotContainsString('RECURSIVE', $sql);
    }

    /**
     * WINDOW clause must survive compilation on the 12c+ compiler.
     *
     * @return void
     */
    public function testWindowClausePresent(): void
    {
        $connection = $this->_oracleConnection();
        $query = $connection->selectQuery()
            ->select(['id'])
            ->from('articles')
            ->window('w', fn($window) => $window->orderBy('id'));

        $this->assertStringContainsString('WINDOW', $query->sql());
    }

    /**
     * INTERSECT clause must survive compilation.
     *
     * @return void
     */
    public function testIntersectClausePresent(): void
    {
        $connection = $this->_oracleConnection();
        $other = $connection->selectQuery()->select(['id'])->from('authors');
        $query = $connection->selectQuery()->select(['id'])->from('articles')->intersect($other);

        $this->assertStringContainsString('INTERSECT', $query->sql());
    }

    /**
     * Legacy (<12c) compiler must emit HAVING.
     *
     * @return void
     */
    public function testHavingOnLegacyCompiler(): void
    {
        $connection = $this->_oracleConnection();
        $query = $connection->selectQuery()
            ->select(['author_id'])
            ->from('articles')
            ->groupBy(['author_id'])
            ->having(['author_id >' => 1]);

        $sql = (new OracleCompiler())->compile($query, new ValueBinder());
        $this->assertStringContainsString('HAVING', $sql);
    }

    /**
     * ORDER BY alias columns must be rescued into GROUP BY (ORA-00979 fix).
     *
     * @return void
     */
    public function testGroupByRescueOrderAlias(): void
    {
        $connection = $this->_oracleConnection();
        $query = $connection->selectQuery()
            ->select(['id', 'sort_key' => new IdentifierExpression('Articles.title')])
            ->from(['Articles' => 'articles'])
            ->groupBy(['Articles.id'])
            ->orderBy(['sort_key' => 'ASC']);

        $sql = $query->sql();
        $group = $query->clause('group');
        $this->assertNotEmpty($group);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('Articles', $sql);
        // The bare sort-key column must have been appended to GROUP BY.
        $found = false;
        foreach ((array)$group as $g) {
            $ref = $g instanceof IdentifierExpression ? $g->getIdentifier() : (string)$g;
            if (strtolower(trim($ref, '"')) === 'articles.title') {
                $found = true;
            }
        }

        $this->assertTrue($found, 'sort_key column was not rescued into GROUP BY');
    }

    /**
     * Bare columns nested in scalar functions (e.g. LENGTH(title)) must be
     * rescued into GROUP BY; the function itself is not an aggregate.
     *
     * @return void
     */
    public function testGroupByRescueScalarFunction(): void
    {
        $connection = $this->_oracleConnection();
        $query = $connection->selectQuery()
            ->select([
                'id',
                'title_length' => $query->func()->length(['Articles.title' => 'identifier']),
            ])
            ->from(['Articles' => 'articles'])
            ->groupBy(['Articles.id'])
            ->having(['title_length >' => 0]);

        $query->sql();

        $found = false;
        foreach ((array)$query->clause('group') as $g) {
            $ref = $g instanceof IdentifierExpression ? $g->getIdentifier() : (string)$g;
            if (strtolower(trim($ref, '"')) === 'articles.title') {
                $found = true;
            }
        }

        $this->assertTrue($found, 'bare column inside LENGTH() was not rescued into GROUP BY');
    }

    /**
     * Genuine aggregates must not be added to GROUP BY.
     *
     * @return void
     */
    public function testGroupByRescueSkipsAggregates(): void
    {
        $connection = $this->_oracleConnection();
        $query = $connection->selectQuery()
            ->select(['author_id', 'total' => $query->func()->count('*')])
            ->from('articles')
            ->groupBy(['author_id']);

        $query->sql();
        $this->assertCount(1, (array)$query->clause('group'));
    }

    /**
     * StringAggExpression must compile to Oracle LISTAGG.
     *
     * @return void
     */
    public function testStringAggCompilesToListagg(): void
    {
        $connection = $this->_oracleConnection();
        $query = $connection->selectQuery()
            ->select(['tags' => $query->func()->stringAgg(['title' => 'identifier', "', '"])])
            ->from('articles')
            ->groupBy(['author_id']);

        $sql = $query->sql();
        $this->assertStringContainsString('LISTAGG', $sql);
        $this->assertStringNotContainsString('STRING_AGG', $sql);
    }

    /**
     * supports() matrix must match real Oracle syntax.
     *
     * @return void
     */
    public function testSupportsMatrix(): void
    {
        $driver12 = new OraclePDO(['server_version' => 12]);
        $this->assertTrue($driver12->supports(DriverFeatureEnum::STRING_AGG));
        $this->assertFalse($driver12->supports(DriverFeatureEnum::GROUP_CONCAT));
        // EXCEPT maps to MINUS via the compiler override.
        $this->assertTrue($driver12->supports(DriverFeatureEnum::EXCEPT));
        $this->assertFalse($driver12->supports(DriverFeatureEnum::EXCEPT_ALL));
        $this->assertTrue($driver12->supports(DriverFeatureEnum::INTERSECT));
        $this->assertFalse($driver12->supports(DriverFeatureEnum::INTERSECT_ALL));
        $this->assertTrue($driver12->supports(DriverFeatureEnum::CTE));
        // Named WINDOW clause needs Oracle 21c+.
        $this->assertFalse($driver12->supports(DriverFeatureEnum::WINDOW));
        $driver21 = new OraclePDO(['server_version' => 21]);
        $this->assertTrue($driver21->supports(DriverFeatureEnum::WINDOW));
    }

    /**
     * Mocked Oracle driver for schema unit checks.
     *
     * @return \CakeDC\OracleDriver\Database\Driver\OraclePDO
     */
    protected function _getMockedDriver(): OraclePDO
    {
        $driver = Mockery::mock(OraclePDO::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $driver->__construct([]);

        $driver->shouldReceive('connect')->andReturnNull();
        $driver->shouldReceive('isAutoQuotingEnabled')->andReturn(true);
        $driver->shouldReceive('quoteIdentifier')->andReturnUsing(fn(string $id): string => '"' . str_replace('"', '', $id) . '"');
        $driver->shouldReceive('schemaValue')->andReturnUsing(function (mixed $v): string {
            if (is_bool($v)) {
                return $v ? 'TRUE' : 'FALSE';
            }

            if (is_int($v)) {
                return (string)$v;
            }

            if ($v === null) {
                return 'NULL';
            }

            return "'" . str_replace("'", "''", (string)$v) . "'";
        });
        $driver->shouldReceive('config')->andReturn(['case' => 'upper', 'autoincrement' => false]);

        return $driver;
    }

    /**
     * New column types must generate Oracle DDL instead of throwing.
     *
     * @return void
     */
    public function testColumnSqlNewTypes(): void
    {
        $schema = new OracleSchema($this->_getMockedDriver());
        $table = (new TableSchema('t'))
            ->addColumn('tz', ['type' => TableSchema::TYPE_TIMESTAMP_TIMEZONE])
            ->addColumn('c', ['type' => TableSchema::TYPE_CHAR, 'length' => 10])
            ->addColumn('u', ['type' => TableSchema::TYPE_NATIVE_UUID])
            ->addColumn('j', ['type' => TableSchema::TYPE_JSON]);

        $this->assertStringContainsString('WITH TIME ZONE', $schema->columnSql($table, 'tz'));
        $this->assertStringContainsString('CHAR(10)', $schema->columnSql($table, 'c'));
        $this->assertStringContainsString('RAW(16)', $schema->columnSql($table, 'u'));
        $this->assertStringContainsString('CLOB', $schema->columnSql($table, 'j'));
    }

    /**
     * Timestamp/datetime defaults (e.g. CURRENT_TIMESTAMP) must be emitted.
     *
     * @return void
     */
    public function testColumnSqlTimestampDefault(): void
    {
        $schema = new OracleSchema($this->_getMockedDriver());
        $table = (new TableSchema('t'))
            ->addColumn('created', ['type' => TableSchema::TYPE_TIMESTAMP, 'default' => 'CURRENT_TIMESTAMP', 'null' => false]);

        $this->assertStringContainsString('DEFAULT CURRENT_TIMESTAMP', $schema->columnSql($table, 'created'));
    }

    /**
     * Raw Oracle defaults must be normalized on reflection.
     *
     * @return void
     */
    public function testConvertColumnDescriptionDefaultNormalization(): void
    {
        $schema = new OracleSchema($this->_getMockedDriver());

        $table = new TableSchema('t');
        $schema->convertColumnDescription($table, [
            'name' => 'a', 'type' => 'VARCHAR2', 'char_length' => 10,
            'data_precision' => null, 'data_scale' => null,
            'null' => 'Y', 'default' => 'NULL  ', 'comment' => null,
        ]);
        $this->assertNull($table->getColumn('a')['default']);

        $table = new TableSchema('t');
        $schema->convertColumnDescription($table, [
            'name' => 'b', 'type' => 'NUMBER', 'char_length' => 22,
            'data_precision' => 10, 'data_scale' => 0,
            'null' => 'Y', 'default' => '"SCHEMA"."SEQ_X".nextval', 'comment' => null,
        ]);
        $this->assertNull($table->getColumn('b')['default']);

        $table = new TableSchema('t');
        $schema->convertColumnDescription($table, [
            'name' => 'c', 'type' => 'TIMESTAMP(6) WITH TIME ZONE', 'char_length' => null,
            'data_precision' => null, 'data_scale' => 6,
            'null' => 'Y', 'default' => null, 'comment' => null,
        ]);
        $this->assertSame(TableSchema::TYPE_TIMESTAMP_TIMEZONE, $table->getColumn('c')['type']);

        $table = new TableSchema('t');
        $schema->convertColumnDescription($table, [
            'name' => 'd', 'type' => 'JSON', 'char_length' => null,
            'data_precision' => null, 'data_scale' => null,
            'null' => 'Y', 'default' => null, 'comment' => null,
        ]);
        $this->assertSame(TableSchema::TYPE_JSON, $table->getColumn('d')['type']);
    }

    /**
     * Foreign-key ON UPDATE must be NO ACTION (Oracle has no ON UPDATE clause).
     *
     * @return void
     */
    public function testConvertForeignKeyUpdateNoAction(): void
    {
        $schemaDialect = new OracleSchema($this->_getMockedDriver());
        $table = (new TableSchema('articles'))->addColumn('author_id', ['type' => 'integer']);
        $schemaDialect->convertForeignKeyDescription($table, [
            'column_name' => 'author_id',
            'constraint_name' => 'author_fk',
            'referenced_owner' => 'TEST',
            'referenced_table_name' => 'authors',
            'referenced_column_name' => 'id',
            'delete_rule' => 'CASCADE',
            'deferrable' => 'NOT DEFERRABLE',
            'deferred' => 'IMMEDIATE',
        ]);

        $constraint = $table->getConstraint('author_fk');
        $this->assertSame(TableSchema::ACTION_NO_ACTION, $constraint['update']);
        $this->assertSame(TableSchema::ACTION_CASCADE, $constraint['delete']);
    }

    /**
     * BITMAP indexes must record their access method.
     *
     * @return void
     */
    public function testConvertIndexBitmapAccessMethod(): void
    {
        $schemaDialect = new OracleSchema($this->_getMockedDriver());
        $table = (new TableSchema('articles'))->addColumn('published', ['type' => 'boolean']);
        $schemaDialect->convertIndexDescription($table, [
            'name' => 'published_bmx',
            'column_name' => 'published',
            'type' => 'BITMAP',
            'is_primary' => null,
            'is_unique' => 0,
        ]);

        $index = $table->getIndex('published_bmx');
        $this->assertSame('BITMAP', $index['accessMethod'] ?? null);
    }

    /**
     * Both compilers must expose the full modern SELECT clause list.
     *
     * @return void
     */
    public function testSelectParts(): void
    {
        foreach ([new OracleCompiler(), new Oracle12Compiler()] as $compiler) {
            $prop = new ReflectionProperty($compiler, '_selectParts');
            $parts = $prop->getValue($compiler);
            foreach (['with', 'window', 'except', 'intersect'] as $clause) {
                $this->assertContains($clause, $parts, $compiler::class . " misses $clause");
            }
        }
    }
}
