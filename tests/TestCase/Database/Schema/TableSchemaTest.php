<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Test\TestCase\Database\Schema;

use Cake\Database\Exception\DatabaseException;
use Cake\Database\Schema\TableSchema;
use Cake\Database\TypeFactory;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test case for Table
 */
class TableSchemaTest extends TestCase
{
    public bool $autoFixtures = true;

    protected array $fixtures = [
        'core.Articles',
        'core.Tags',
        // 'core.ArticlesTags',
        'plugin.CakeDC/OracleDriver.ArticlesTags',
        // 'core.Orders',
        // 'core.Products'
    ];

    /**
     * @var array<string, string>|null
     */
    protected ?array $_map = null;

    protected function setUp(): void
    {
        $map = TypeFactory::getMap();
        $this->_map = is_array($map) ? $map : null;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->getTableLocator()->clear();
        TypeFactory::clear();
        if ($this->_map !== null) {
            TypeFactory::setMap($this->_map);
        }

        parent::tearDown();
    }

    /**
     * Test construction with columns
     *
     * @return void
     */
    public function testConstructWithColumns(): void
    {
        $columns = [
            'id' => [
                'type' => 'integer',
                'length' => 11,
            ],
            'title' => [
                'type' => 'string',
                'length' => 255,
            ],
        ];
        $table = new TableSchema('articles', $columns);
        $this->assertEquals(['id', 'title'], $table->columns());
    }

    /**
     * Test adding columns.
     *
     * @return void
     */
    public function testAddColumn(): void
    {
        $table = new TableSchema('articles');
        $result = $table->addColumn('title', [
            'type' => 'string',
            'length' => 25,
            'null' => false,
        ]);
        $this->assertSame($table, $result);
        $this->assertEquals(['title'], $table->columns());

        $result = $table->addColumn('body', 'text');
        $this->assertSame($table, $result);
        $this->assertEquals(['title', 'body'], $table->columns());
    }

    /**
     * Test hasColumn() method.
     *
     * @return void
     */
    public function testHasColumn(): void
    {
        $schema = new TableSchema('articles', [
            'title' => 'string',
        ]);

        $this->assertTrue($schema->hasColumn('title'));
        $this->assertFalse($schema->hasColumn('body'));
    }

    /**
     * Test removing columns.
     *
     * @return void
     */
    public function testRemoveColumn(): void
    {
        $table = new TableSchema('articles');
        $result = $table->addColumn('title', [
            'type' => 'string',
            'length' => 25,
            'null' => false,
        ])->removeColumn('title')
        ->removeColumn('unknown');

        $this->assertSame($table, $result);
        $this->assertEquals([], $table->columns());
        $this->assertNull($table->getColumn('title'));
        $this->assertSame([], $table->typeMap());
    }

    /**
     * Test isNullable method
     *
     * @return void
     */
    public function testIsNullable(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('title', [
            'type' => 'string',
            'length' => 25,
            'null' => false,
        ])->addColumn('tagline', [
            'type' => 'string',
            'length' => 25,
            'null' => true,
        ]);
        $this->assertFalse($table->isNullable('title'));
        $this->assertTrue($table->isNullable('tagline'));
        $this->assertTrue($table->isNullable('missing'));
    }

    /**
     * Test columnType method
     *
     * @return void
     */
    public function testColumnType(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('title', [
            'type' => 'string',
            'length' => 25,
            'null' => false,
        ]);
        $this->assertEquals('string', $table->getColumnType('title'));
        $this->assertNull($table->getColumnType('not there'));
    }

    /**
     * Test setColumnType setter method
     *
     * @return void
     */
    public function testSetColumnType(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('title', [
            'type' => 'string',
            'length' => 25,
            'null' => false,
        ]);
        $this->assertEquals('string', $table->getColumnType('title'));
        $table->setColumnType('title', 'json');
        $this->assertEquals('json', $table->getColumnType('title'));
    }

    /**
     * Tests getting the baseType as configured when creating the column
     *
     * @return void
     */
    public function testBaseColumnType(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('title', [
            'type' => 'json',
            'baseType' => 'text',
            'length' => 25,
            'null' => false,
        ]);
        $this->assertEquals('json', $table->getColumnType('title'));
        $this->assertEquals('text', $table->baseColumnType('title'));
    }

    /**
     * Tests getting the base type as it is retuned by the Type class
     *
     * @return void
     */
    public function testBaseColumnTypeInherited(): void
    {
        TypeFactory::map('foo', FooType::class);
        $table = new TableSchema('articles');
        $table->addColumn('thing', [
            'type' => 'foo',
            'null' => false,
        ]);
        $this->assertEquals('foo', $table->getColumnType('thing'));
        $this->assertEquals('integer', $table->baseColumnType('thing'));
    }

    /**
     * Attribute keys should be filtered and have defaults set.
     *
     * @return void
     */
    public function testAddColumnFiltersAttributes(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('title', [
            'type' => 'string',
        ]);
        $result = $table->getColumn('title');
        $expected = [
            'type' => 'string',
            'length' => null,
            'precision' => null,
            'default' => null,
            'null' => null,
            'comment' => null,
            'collate' => null,
        ];
        $this->assertEquals($expected, $result);

        $table->addColumn('author_id', [
            'type' => 'integer',
        ]);
        $result = $table->getColumn('author_id');
        $expected = [
            'type' => 'integer',
            'length' => null,
            'precision' => null,
            'default' => null,
            'null' => null,
            'unsigned' => null,
            'comment' => null,
            'autoIncrement' => false,
            'generated' => null,
        ];
        $this->assertEquals($expected, $result);

        $table->addColumn('amount', [
            'type' => 'decimal',
        ]);
        $result = $table->getColumn('amount');
        $expected = [
            'type' => 'decimal',
            'length' => null,
            'precision' => null,
            'default' => null,
            'null' => null,
            'unsigned' => null,
            'comment' => null,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test reading default values.
     *
     * @return void
     */
    public function testDefaultValues(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('id', [
            'type' => 'integer',
            'default' => 0,
        ])->addColumn('title', [
            'type' => 'string',
            'default' => 'A title',
        ])->addColumn('name', [
            'type' => 'string',
            'null' => false,
            'default' => null,
        ])->addColumn('body', [
            'type' => 'text',
            'null' => true,
            'default' => null,
        ]);
        $result = $table->defaultValues();
        $expected = [
            'id' => 0,
            'title' => 'A title',
            'body' => null,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test adding an constraint.
     * >
     *
     * @return void
     */
    public function testAddConstraint(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('id', [
            'type' => 'integer',
        ]);
        $result = $table->addConstraint('primary', [
            'type' => 'primary',
            'columns' => ['id'],
        ]);
        $this->assertSame($result, $table);
        $this->assertEquals(['primary'], $table->constraints());
    }

    /**
     * Test adding an constraint with an overlapping unique index
     * >
     *
     * @return void
     */
    public function testAddConstraintOverwriteUniqueIndex(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('project_id', [
            'type' => 'integer',
            'default' => null,
            'limit' => 11,
            'null' => false,
        ])->addColumn('id', [
            'type' => 'integer',
            'autoIncrement' => true,
            'limit' => 11,
        ])->addColumn('user_id', [
            'type' => 'integer',
            'default' => null,
            'limit' => 11,
            'null' => false,
        ])->addConstraint('users_idx', [
            'type' => 'unique',
            'columns' => ['project_id', 'user_id'],
        ])->addConstraint('users_idx', [
            'type' => 'foreign',
            'references' => ['users', 'project_id', 'id'],
            'columns' => ['project_id', 'user_id'],
        ]);
        $this->assertEquals(['users_idx'], $table->constraints());
    }

    /**
     * Dataprovider for invalid addConstraint calls.
     *
     * @return array
     */
    public static function addConstraintErrorProvider(): array
    {
        return [
            // No properties
            [[]],
            // Empty columns
            [['columns' => '', 'type' => TableSchema::CONSTRAINT_UNIQUE]],
            [['columns' => [], 'type' => TableSchema::CONSTRAINT_UNIQUE]],
            // Missing column
            [['columns' => ['derp'], 'type' => TableSchema::CONSTRAINT_UNIQUE]],
            // Invalid type
            [['columns' => 'author_id', 'type' => 'derp']],
        ];
    }

    /**
     * Test that an exception is raised when constraints
     * are added for fields that do not exist.
     *
     * @return void
     */
    #[DataProvider('addConstraintErrorProvider')]
    public function testAddConstraintError(array $props): void
    {
        $this->expectException(DatabaseException::class);
        $table = new TableSchema('articles');
        $table->addColumn('author_id', 'integer');
        $table->addConstraint('author_idx', $props);
    }

    /**
     * Test adding an index.
     *
     * @return void
     */
    public function testAddIndex(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('title', [
            'type' => 'string',
        ]);
        $result = $table->addIndex('faster', [
            'type' => 'index',
            'columns' => ['title'],
        ]);
        $this->assertSame($result, $table);
        $this->assertEquals(['faster'], $table->indexes());
    }

    /**
     * Dataprovider for invalid addIndex calls
     *
     * @return array
     */
    public static function addIndexErrorProvider(): array
    {
        return [
            // Empty
            [[]],
            // Invalid type
            [['columns' => 'author_id', 'type' => 'derp']],
            // No columns
            [['columns' => ['not_there'], 'type' => TableSchema::INDEX_INDEX]],
        ];
    }

    /**
     * Test that an exception is raised when indexes
     * are added for fields that do not exist.
     *
     * @return void
     */
    #[DataProvider('addIndexErrorProvider')]
    public function testAddIndexError(array $props): void
    {
        $this->expectException(DatabaseException::class);
        $table = new TableSchema('articles');
        $table->addColumn('author_id', 'integer');
        $table->addIndex('author_idx', $props);
    }

    /**
     * Test adding different kinds of indexes.
     *
     * @return void
     */
    public function testAddIndexTypes(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('id', 'integer')
            ->addColumn('title', 'string')
            ->addColumn('author_id', 'integer');

        $table->addIndex('author_idx', [
                'columns' => ['author_id'],
                'type' => 'index',
            ])->addIndex('texty', [
                'type' => 'fulltext',
                'columns' => ['title'],
            ]);

        $this->assertEquals(
            ['author_idx', 'texty'],
            $table->indexes(),
        );
    }

    /**
     * Test getting the primary key.
     *
     * @return void
     */
    public function testPrimaryKey(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('id', 'integer')
            ->addColumn('title', 'string')
            ->addColumn('author_id', 'integer')
            ->addConstraint('author_idx', [
                'columns' => ['author_id'],
                'type' => 'unique',
            ])->addConstraint('primary', [
                'type' => 'primary',
                'columns' => ['id'],
            ]);
        $this->assertEquals(['id'], $table->getPrimaryKey());

        $table = new TableSchema('articles');
        $table->addColumn('id', 'integer')
            ->addColumn('title', 'string')
            ->addColumn('author_id', 'integer');
        $this->assertEquals([], $table->getPrimaryKey());
    }

    /**
     * Test the setOptions/getOptions methods.
     *
     * @return void
     */
    public function testOptions(): void
    {
        $table = new TableSchema('articles');
        $options = [
            'engine' => 'InnoDB',
        ];
        $return = $table->setOptions($options);
        $this->assertInstanceOf(TableSchema::class, $return);
        $this->assertEquals($options, $table->getOptions());
    }

    /**
     * Test the options method.
     *
     * @return void
     */
    #[Group('deprecated')]
    public function testOptionsDeprecated(): void
    {
        $table = new TableSchema('articles');
        $options = [
            'engine' => 'InnoDB',
        ];
        $return = $table->setOptions($options);
        $this->assertInstanceOf(TableSchema::class, $return);
        $this->assertEquals($options, $table->getOptions());
    }

    /**
     * Add a basic foreign key constraint.
     *
     * @return void
     */
    public function testAddConstraintForeignKey(): void
    {
        $table = new TableSchema('articles');
        $table->addColumn('author_id', 'integer')
            ->addConstraint('author_id_idx', [
                'type' => TableSchema::CONSTRAINT_FOREIGN,
                'columns' => ['author_id'],
                'references' => ['authors', 'id'],
                'update' => 'cascade',
                'delete' => 'cascade',
            ]);
        $this->assertEquals(['author_id_idx'], $table->constraints());
    }

    /**
     * Test single column foreign keys constraint support
     *
     * @return void
     */
    public function testConstraintForeignKey(): void
    {
        $this->markTestSkipped('');
    }

    /**
     * Test composite foreign keys support
     *
     * @return void
     */
    public function testConstraintForeignKeyTwoColumns(): void
    {
        $this->markTestSkipped('');
    }

    /**
     * Provider for exceptionally bad foreign key data.
     *
     * @return array
     */
    public static function badForeignKeyProvider(): array
    {
        return [
            'references is bad' => [[
                'type' => TableSchema::CONSTRAINT_FOREIGN,
                'columns' => ['author_id'],
                'references' => ['authors'],
                'delete' => 'derp',
            ]],
            'bad update value' => [[
                'type' => TableSchema::CONSTRAINT_FOREIGN,
                'columns' => ['author_id'],
                'references' => ['authors', 'id'],
                'update' => 'derp',
            ]],
            'bad delete value' => [[
                'type' => TableSchema::CONSTRAINT_FOREIGN,
                'columns' => ['author_id'],
                'references' => ['authors', 'id'],
                'delete' => 'derp',
            ]],
        ];
    }

    /**
     * Add a foreign key constraint with bad data
     *
     * @return void
     */
    #[DataProvider('badForeignKeyProvider')]
    public function testAddConstraintForeignKeyBadData(array $data): void
    {
        $this->expectException(DatabaseException::class);
        $table = new TableSchema('articles');
        $table->addColumn('author_id', 'integer')
            ->addConstraint('author_id_idx', $data);
    }

    /**
     * Tests the setTemporary() & isTemporary() method
     *
     * @return void
     */
    public function testSetTemporary(): void
    {
        $table = new TableSchema('articles');
        $this->assertFalse($table->isTemporary());
        $this->assertSame($table, $table->setTemporary(true));
        $this->assertTrue($table->isTemporary());

        $table->setTemporary(false);
        $this->assertFalse($table->isTemporary());
    }

    /**
     * Assertion for comparing a regex pattern against a query having its identifiers
     * quoted. It accepts queries quoted with the characters `<` and `>`. If the third
     * parameter is set to true, it will alter the pattern to both accept quoted and
     * unquoted queries
     *
     * @param string $pattern
     * @param string $query the result to compare against
     * @param bool $optional
     * @return void
     */
    public function assertQuotedQuery($pattern, string $query, $optional = false): void
    {
        if ($optional) {
            $optional = '?';
        }

        $pattern = str_replace('<', '[`"\[]' . $optional, $pattern);
        $pattern = str_replace('>', '[`"\]]' . $optional, $pattern);
        $this->assertMatchesRegularExpression('#' . $pattern . '#', $query);
    }
}
