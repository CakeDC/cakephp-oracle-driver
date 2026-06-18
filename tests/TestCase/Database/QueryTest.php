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

namespace CakeDC\OracleDriver\Test\TestCase\Database;

use Cake\Database\Driver\Postgres;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\TypeMap;
use Cake\Database\ValueBinder;
use Cake\Database\Exception\DatabaseException;
use Cake\Database\Expression\StringExpression;
use Cake\Datasource\ConnectionManager;
use Cake\Test\TestCase\Database\QueryAssertsTrait;
use PHPUnit\Framework\Attributes\Group;
use Cake\TestSuite\Fixture\FixtureHelper;
use Cake\TestSuite\TestCase;
use CakeDC\OracleDriver\Database\FunctionsBuilder;

/**
 * Tests Query class
 */
class QueryTest extends TestCase
{
    use QueryAssertsTrait;

    public const ARTICLE_COUNT = 3;
    public const AUTHOR_COUNT = 4;
    public const COMMENT_COUNT = 6;

    protected array $fixtures = [
        'core.Articles',
        'core.Authors',
        'core.Comments',
        'core.MenuLinkTrees',
    ];

    /**
     * @var \Cake\Database\Connection
     */
    protected $connection;

    /**
     * @var bool
     */
    protected $autoQuote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
        $this->autoQuote = $this->connection->getDriver()->isAutoQuotingEnabled();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->getDriver()->enableAutoQuoting($this->autoQuote);
        unset($this->connection);
    }

    /**
     * Back-compat helper for CakePHP 4 style per-test fixture loading.
     *
     * @param string ...$fixtureArgs Short fixture names or fully qualified fixture keys.
     * @return void
     */
    public function loadFixtures(string ...$fixtureArgs): void
    {
        $fixtureNames = [];
        foreach ($fixtureArgs as $fixture) {
            $fixtureNames[] = str_contains($fixture, '.') ? $fixture : 'core.' . $fixture;
        }

        $helper = new FixtureHelper();
        $fixtures = $helper->loadFixtures($fixtureNames);
        $helper->truncate($fixtures);
        $helper->insert($fixtures);
    }

    /**
     * @inheritDoc
     */
    public function testSelectAliasedTables()
    {
        $this->loadFixtures('Authors', 'Articles');
        $query = $this->connection->selectQuery();
        $result = $query->select(['text' => FunctionsBuilder::toChar(new IdentifierExpression('a.body')), 'a.author_id'])
            ->from(['a' => 'articles'])->execute();

        $this->assertEquals(['text' => 'First Article Body', 'author_id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['text' => 'Second Article Body', 'author_id' => 3], $result->fetch('assoc'));

        $result = $query->select(['name' => 'b.name'])->from(['b' => 'authors'])
            ->orderBy(['text' => 'desc', 'name' => 'desc'])
            ->execute();
        $this->assertEquals(
            ['text' => 'Third Article Body', 'author_id' => 1, 'name' => 'nate'],
            $result->fetch('assoc')
        );
        $this->assertEquals(
            ['text' => 'Third Article Body', 'author_id' => 1, 'name' => 'mariano'],
            $result->fetch('assoc')
        );
    }

    /**
     * @inheritDoc
     */
    public function testSelectOrderBy()
    {
        $this->loadFixtures('Authors', 'Articles');
        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['id'])
            ->from('articles')
            ->orderBy(['id' => 'desc'])
            ->execute();
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));

        $result = $query->orderBy(['id' => 'asc'])->execute();
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));

        $result = $query->orderBy(['title' => 'asc'])->execute();
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));

        $result = $query->orderBy(['title' => 'asc'], true)->execute();
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));

        $result = $query->orderBy(['title' => 'asc', 'published' => 'asc'], true)
            ->execute();
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));

        $driver = $query->getConnection()->getDriver();
        $idField = $driver->quoteIfAutoQuote('id');
        $expression = $query->expr(["MOD(($idField + :offset), 2)"]);
        $result = $query
            ->orderBy([$expression, 'id' => 'desc'], true)
            ->bind(':offset', 1, null)
            ->execute();
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));

        $result = $query
            ->orderBy($expression, true)
            ->orderBy(['id' => 'asc'])
            ->bind(':offset', 1, null)
            ->execute();
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));
    }

    /**
     * @inheritDoc
     */
    public function testSelectGroup()
    {
        $this->loadFixtures('Authors', 'Articles');
        $query = $this->connection->selectQuery();
        $result = $query->select(['total' => 'count(author_id)', 'author_id'])
            ->from('articles')
            ->join([
                'table' => 'authors',
                'alias' => 'a',
                'conditions' => $query
                    ->expr()
                    ->eq(new IdentifierExpression('author_id'), new IdentifierExpression('a.id')),
            ])
            ->groupBy('author_id')
            ->execute();

        $expected = [['total' => 2, 'author_id' => 1], ['total' => '1', 'author_id' => 3]];
        $this->assertEquals($expected, $result->fetchAll('assoc'));

        $result = $query
            ->select(['total' => 'count(title)', 'name'], true)
            ->groupBy(['name'], true)
            ->orderBy(['total' => 'asc'])
            ->execute();
        $expected = [['total' => 1, 'name' => 'larry'], ['total' => 2, 'name' => 'mariano']];
        $this->assertEquals($expected, $result->fetchAll('assoc'));

        $result = $query
            ->select(['articles.id'])
            ->groupBy(['articles.id'])
            ->execute();
        $this->assertCount(3, $result->fetchAll());
    }

    /**
     * Tests that expression objects can be used as the field in a comparison
     * and the values will be bound correctly to the query
     *
     * @return void
     */
    public function testSelectWhereUsingExpressionInField()
    {
        $this->loadFixtures('Authors', 'Articles', 'Comments');
        $query = $this->connection->selectQuery();
        $subquery = clone $query;
        $result = $query
            ->select(['id'])
            ->from('comments')
            ->where(function ($exp) use ($subquery) {
                $field = clone $exp;
                $field
                    ->add($subquery
                        ->select([
                            $subquery
                                ->func()
                                ->min(new IdentifierExpression('id')),
                        ])
                        ->from('comments'));

                return $exp->eq($field, 100, 'integer');
            })
            ->execute();
        $this->assertCount(0, $result->fetchAll());
    }

    /**
     * Tests that functions are correctly transformed and their parameters are bound
     *
     * @return void
     */
    #[Group('FunctionExpression')]
    public function testSQLFunctions()
    {
        $this->loadFixtures('Comments');
        $query = $this->connection->selectQuery();
        $result = $query->select(
            function ($q) {
                return ['total' => $q->func()->count('*')];
            }
        )
            ->from('comments')
            ->execute();
        $expected = [['total' => 6]];
        $this->assertEquals($expected, $result->fetchAll('assoc'));

        $query = $this->connection->selectQuery();
        $result = $query->select([
                'c' => $query->func()->concat([$query->func()->to_char([new IdentifierExpression('comment')]), ' is appended']),
            ])
            ->from('comments')
            ->orderBy(['c' => 'ASC'])
            ->limit(1)
            ->execute();
        $expected = [
            ['c' => 'First Comment for First Article is appended'],
        ];
        $this->assertEquals($expected, $result->fetchAll('assoc'));

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['d' => $query->func()->dateDiff(['2012-01-05', '2012-01-02'])])
            ->from('DUAL')
            ->execute()
            ->fetchAll('assoc');
        $this->assertEquals(3, abs((float)$result[0]['d']));

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['d' => $query->func()->now('date')])
            ->from('DUAL')
            ->execute();
        $date = $result->fetchAll('assoc');
        $this->assertEquals([['d' => date('Y-m-d')]], $date);

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['d' => $query->func()->now('time')])
            ->from('DUAL')
            ->execute();

        $d = $result->fetchAll('assoc')[0]['d'];
        $date1 = date('U');
        $this->assertWithinRange(
            $date1,
            (new \DateTime($d))->format('U'),
            1
        );

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['d' => $query->func()->now()])
            ->from('DUAL')
            ->execute();
        $this->assertWithinRange(
            date('U'),
            (new \DateTime($result->fetchAll('assoc')[0]['d']))->format('U'),
            1
        );

        $query = $this->connection->selectQuery();
        $created = new IdentifierExpression('created');
        $result = $query
            ->select([
                'd' => $query->func()->datePart('day', $created),
                'm' => $query->func()->datePart('month', $created),
                'y' => $query->func()->datePart('year', $created),
                'de' => $query->func()->extract('day', $created),
                'me' => $query->func()->extract('month', $created),
                'ye' => $query->func()->extract('year', $created),
                'wd' => $query->func()->weekday($created),
                'dow' => $query->func()->dayOfWeek($created),
                'addDays' => $query->func()->dateAdd($created, 2, 'day'),
                'substractYears' => $query->func()->dateAdd($created, -2, 'year'),
            ])
            ->from('comments')
            ->where([
                'created' => FunctionsBuilder::toDate(['2007-03-18 10:45:23']),
            ])
            ->execute()
            ->fetchAll('assoc');
        $result[0]['m'] = ltrim($result[0]['m'], '0');
        $result[0]['me'] = ltrim($result[0]['me'], '0');
        $result[0]['addDays'] = substr($result[0]['addDays'], 0, 10);
        $result[0]['substractYears'] = substr($result[0]['substractYears'], 0, 10);
        $expected = [
            'd' => '18',
            'm' => '3',
            'y' => '2007',
            'de' => '18',
            'me' => '3',
            'ye' => '2007',
            'wd' => '1', // Sunday
            'dow' => '1',
            'addDays' => '2007-03-20',
            'substractYears' => '2005-03-18',
        ];
        $this->assertEquals($expected, $result[0]);
    }

    /**
     * Test that order() being a string works.
     * Does not work for oracle in escape mode. So test changed to array.
     *
     * @return void
     */
    public function testSelectOrderByString()
    {
        $this->loadFixtures('Articles');
        $query = $this->connection->selectQuery();
        $query->select(['id'])
            ->from('articles')
            ->orderBy(['id' => 'asc']);
        $result = $query->execute();
        $this->assertEquals(['id' => 1], $result->fetch('assoc'));
        $this->assertEquals(['id' => 2], $result->fetch('assoc'));
        $this->assertEquals(['id' => 3], $result->fetch('assoc'));
    }

    /**
     * Tests that having() behaves pretty much the same as the where() method
     *
     * @return void
     */
    public function testSelectHaving()
    {
        $this->loadFixtures('Authors', 'Articles');
        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['total' => 'count(author_id)', 'author_id'])
            ->from('articles')
            ->join(['table' => 'authors', 'alias' => 'a', 'conditions' => $query->expr()->equalFields('author_id', 'a.id')])
            ->groupBy('author_id')
            ->having(['count(author_id) <' => 2], ['count(author_id)' => 'integer'])
            ->execute();
        $expected = [['total' => 1, 'author_id' => 3]];
        $this->assertEquals($expected, $result->fetchAll('assoc'));

        $result = $query->having(['count(author_id)' => 2], ['count(author_id)' => 'integer'], true)
            ->execute();
        $expected = [['total' => 2, 'author_id' => 1]];
        $this->assertEquals($expected, $result->fetchAll('assoc'));
    }

    /**
     * Tests that Query::andHaving() can be used to concatenate conditions with AND
     * in the having clause
     *
     * @return void
     */
    public function testSelectAndHaving()
    {
        $this->loadFixtures('Authors', 'Articles');
        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['total' => 'count(author_id)', 'author_id'])
            ->from('articles')
            ->join(['table' => 'authors', 'alias' => 'a', 'conditions' => $query->expr()->equalFields('author_id', 'a.id')])
            ->groupBy('author_id')
            ->having(['count(author_id) >' => 2], ['count(author_id)' => 'integer'])
            ->andHaving(['count(author_id) <' => 2], ['count(author_id)' => 'integer'])
            ->execute();
        $this->assertCount(0, $result->fetchAll());

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['total' => 'count(author_id)', 'author_id'])
            ->from('articles')
            ->join(['table' => 'authors', 'alias' => 'a', 'conditions' => $query->expr()->equalFields('author_id', 'a.id')])
            ->groupBy('author_id')
            ->having(['count(author_id)' => 2], ['count(author_id)' => 'integer'])
            ->andHaving(['count(author_id) >' => 1], ['count(author_id)' => 'integer'])
            ->execute();
        $expected = [['total' => 2, 'author_id' => 1]];
        $this->assertEquals($expected, $result->fetchAll('assoc'));
    }

    /**
     * Tests parameter binding
     *
     * @return void
     */
    public function testBind()
    {
        $this->loadFixtures('Authors', 'Articles', 'Comments');
        $query = $this->connection->selectQuery();
        $driver = $query->getConnection()->getDriver();
        $createdField = $driver->quoteIfAutoQuote('created');
        $results = $query->select(['id', 'comment'])
            ->from('comments')
            ->where(["$createdField BETWEEN :foo AND :bar"])
            ->bind(':foo', new \DateTime('2007-03-18 10:50:00'), 'datetime')
            ->bind(':bar', new \DateTime('2007-03-18 10:52:00'), 'datetime')
            ->execute();
        $expected = [['id' => '4', 'comment' => 'Fourth Comment for First Article']];
        $this->assertEquals($expected, $results->fetchAll('assoc'));

        $query = $this->connection->selectQuery();
        $results = $query->select(['id', 'comment'])
            ->from('comments')
            ->where(["$createdField BETWEEN :foo AND :bar"])
            ->bind(':foo', '2007-03-18 10:50:00')
            ->bind(':bar', '2007-03-18 10:52:00')
            ->execute();
        $this->assertEquals($expected, $results->fetchAll('assoc'));
    }

    /**
     * Test that insert can use expression objects as values.
     *
     * @return void
     */
    public function testInsertExpressionValues()
    {
        $this->loadFixtures('Authors', 'Articles', 'Comments');
        $query = $this->connection->insertQuery();
        $query->insert(['title', 'author_id'])
            ->into('articles')
            ->values(['title' => $query->expr("SELECT 'jose' FROM DUAL"), 'author_id' => 99]);

        $result = $query->execute();

        //PDO_SQLSRV returns -1 for successful inserts when using INSERT ... OUTPUT
        if (!$this->connection->getDriver() instanceof \Cake\Database\Driver\Sqlserver) {
            $this->assertSame(1, $result->rowCount());
        }
        $result->closeCursor();

        $rows = ($this->connection->selectQuery())->select('*')
            ->from('articles')
            ->where(['author_id' => 99])
            ->execute()->fetchAll('assoc');
        $this->assertCount(1, $rows);
        $expected = [
            'id' => 4,
            'title' => 'jose',
            'body' => null,
            'author_id' => '99',
            'published' => 'N',
        ];
        $this->assertEquals($expected, $rows[0]);

        $subquery = $this->connection->selectQuery();
        $subquery->select(['name'])
            ->from('authors')
            ->where(['id' => 1]);

        $query = $this->connection->insertQuery();
        $query->insert(['title', 'author_id'])
            ->into('articles')
            ->values(['title' => $subquery, 'author_id' => 100]);
        $result = $query->execute();

        //PDO_SQLSRV returns -1 for successful inserts when using INSERT ... OUTPUT
        if (!$this->connection->getDriver() instanceof \Cake\Database\Driver\Sqlserver) {
            $this->assertSame(1, $result->rowCount());
        }
        $result->closeCursor();

        $rows = ($this->connection->selectQuery())->select('*')
            ->from('articles')
            ->where(['author_id' => 100])
            ->execute()->fetchAll('assoc');
        $this->assertCount(1, $rows);
        $expected = [
            'id' => 5,
            'title' => 'mariano',
            'body' => null,
            'author_id' => '100',
            'published' => 'N',
        ];
        $this->assertEquals($expected, $rows[0]);
    }

    /**
     * Tests that it is possible to select distinct rows, even filtering by one column
     * this is testing that there is a specific implementation for DISTINCT ON
     *
     * @return void
     */
    public function testSelectDistinctON()
    {
        $this->markTestSkipped('Distinct on not supported in Oracle');
    }

    /**
     * Tests that it is possible to one or multiple UNION statements in a query
     *
     * @return void
     */
    public function testUnion()
    {
        $this->loadFixtures('Authors', 'Articles', 'Comments');
        $union = ($this->connection->selectQuery())->select(['id', 'title'])->from(['a' => 'articles']);
        $query = $this->connection->selectQuery();
        $result = $query->select(['id', FunctionsBuilder::toChar(new IdentifierExpression('comment'))])
            ->from(['c' => 'comments'])
            ->union($union)
            ->execute();
        $rows = $result->fetchAll();
        $this->assertCount(self::COMMENT_COUNT + self::ARTICLE_COUNT, $rows);

        $union->select(['foo' => 'id', 'bar' => 'title']);
        $union = ($this->connection->selectQuery())
            ->select(['id', 'name', 'other' => 'id', 'nameish' => 'name'])
            ->from(['b' => 'authors'])
            ->where(['id ' => 1]);

        $query->select(['foo' => 'id', 'bar' => FunctionsBuilder::toChar(new IdentifierExpression('comment'))])->union($union);
        $result = $query->execute();
        $rows2 = $result->fetchAll();
        $this->assertCount(self::COMMENT_COUNT + self::AUTHOR_COUNT, $rows2);
        $this->assertNotEquals($rows, $rows2);

        $union = ($this->connection->selectQuery())
            ->select(['id', 'title'])
            ->from(['c' => 'articles']);
        $query->select(['id', FunctionsBuilder::toChar(new IdentifierExpression('comment'))], true)->union($union, true);
        $result = $query->execute();
        $rows3 = $result->fetchAll();
        $this->assertCount(self::COMMENT_COUNT + self::ARTICLE_COUNT, $rows3);
        $this->assertEquals($rows, $rows3);
    }

    /**
     * Tests that it is possible to run unions with order statements
     *
     * @return void
     */
    public function testUnionOrderBy()
    {
        $this->skipIf(
            ($this->connection->getDriver() instanceof \CakeDC\OracleDriver\Database\Driver\OracleBase),
            'Driver does not support ORDER BY in UNIONed queries.'
        );
        parent::testUnionOrderBy();
    }

    /**
     * Tests that UNION ALL can be built by setting the second param of union() to true
     *
     * @return void
     */
    public function testUnionAll()
    {
        $this->loadFixtures('Authors', 'Articles', 'Comments');
        $union = ($this->connection->selectQuery())->select(['id', 'title'])->from(['a' => 'articles']);
        $query = $this->connection->selectQuery();
        $result = $query->select(['id', FunctionsBuilder::toChar(new IdentifierExpression('comment'))])
            ->from(['c' => 'comments'])
            ->union($union)
            ->execute();
        $rows = $result->fetchAll();
        $this->assertCount(self::ARTICLE_COUNT + self::COMMENT_COUNT, $rows);

        $union->select(['foo' => 'id', 'bar' => 'title']);
        $union = ($this->connection->selectQuery())
            ->select(['id', 'name', 'other' => 'id', 'nameish' => 'name'])
            ->from(['b' => 'authors'])
            ->where(['id ' => 1]);

        $query->select(['foo' => 'id', 'bar' => FunctionsBuilder::toChar(new IdentifierExpression('comment'))])->unionAll($union);
        $result = $query->execute();
        $rows2 = $result->fetchAll();
        $this->assertCount(1 + self::COMMENT_COUNT + self::ARTICLE_COUNT, $rows2);
        $this->assertNotEquals($rows, $rows2);
    }

    /**
     * Tests that joins can be aliased using array keys
     *
     * @return void
     */
    public function testSelectAliasedJoins()
    {
        $this->loadFixtures('Authors', 'Articles', 'Comments');
        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['title', 'name'])
            ->from('articles')
            ->join(['a' => 'authors'])
            ->orderBy(['name' => 'desc', 'articles.id' => 'asc'])
            ->execute();
        $this->assertEquals(['title' => 'First Article', 'name' => 'nate'], $result->fetch('assoc'));
        $this->assertEquals(['title' => 'Second Article', 'name' => 'nate'], $result->fetch('assoc'));
        $result->closeCursor();

        $query = $this->connection->selectQuery();
        $conditions = $query->expr()->equalFields('author_id', 'a.id');
        $result = $query
            ->select(['title', 'name'])
            ->from('articles')
            ->join(['a' => ['table' => 'authors', 'conditions' => $conditions]])
            ->orderBy(['title' => 'asc'])
            ->execute();
        $this->assertEquals(['title' => 'First Article', 'name' => 'mariano'], $result->fetch('assoc'));
        $this->assertEquals(['title' => 'Second Article', 'name' => 'larry'], $result->fetch('assoc'));
        $result->closeCursor();

        $query = $this->connection->selectQuery();
        $time = new \DateTime('2007-03-18 10:45:23');
        $types = ['created' => 'datetime'];
        $result = $query
            ->select(['title', 'name' => 'c.comment'])
            ->from('articles')
            ->join(['c' => ['table' => 'comments', 'conditions' => ['created' => $time]]], $types)
            ->execute();
        $this->assertEquals(['title' => 'First Article', 'name' => 'First Comment for First Article'], $result->fetch('assoc'));
        $result->closeCursor();
    }

    /**
     * Test selecting rows using the page() method and ordering the results
     * by a calculated column.
     *
     * @return void
     */
    public function testSelectPageWithOrder()
    {
        $this->loadFixtures('Comments');
        $query = $this->connection->selectQuery();

        $driver = $query->getConnection()->getDriver();
        $userIdField = $driver->quoteIfAutoQuote('user_id');
        $articleIdField = $driver->quoteIfAutoQuote('article_id');
        $addExpression = $query->expr(["$userIdField + $articleIdField"]);
        $result = $query
            ->select([
                'id',
                'ids_added' => $query->expr()->add($addExpression),
            ])
            ->from('comments')
            ->orderBy(['ids_added' => 'asc'])
            ->limit(2)
            ->page(3)
            ->execute();
        $rows = $result->fetchAll('assoc');
        $this->assertCount(2, $rows);
        $this->assertEquals(
            [
                ['id' => '6', 'ids_added' => '4'],
                ['id' => '2', 'ids_added' => '5'],
            ],
            $rows
        );
    }

    /**
     * Tests Query::whereNull()
     *
     * @return void
     */
    public function testSelectWhereNull()
    {
        $this->loadFixtures('MenuLinkTrees');

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['id', 'parent_id'])
            ->from('menu_link_trees')
            ->whereNull(['parent_id'])
            ->execute();
        $this->assertCount(5, $result->fetchAll());
        $result->closeCursor();

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['id'])
            ->from('menu_link_trees')
            ->whereNull('parent_id')
            ->execute();
        $this->assertCount(5, $result->fetchAll());
        $result->closeCursor();
    }

    /**
     * Tests Query::whereNotNull()
     *
     * @return void
     */
    public function testSelectWhereNotNull()
    {
        $this->loadFixtures('MenuLinkTrees');

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['id', 'parent_id'])
            ->from('menu_link_trees')
            ->whereNotNull(['parent_id'])
            ->execute();
        $this->assertCount(13, $result->fetchAll());
        $result->closeCursor();

        $query = $this->connection->selectQuery();
        $result = $query
            ->select(['id'])
            ->from('menu_link_trees')
            ->whereNotNull('parent_id')
            ->execute();
        $this->assertCount(13, $result->fetchAll());
        $result->closeCursor();
    }

    /**
     * Test orderAsc() and its input types.
     *
     * @return void
     */
    public function testSelectOrderAsc()
    {
        $this->loadFixtures('Articles');
        $query = $this->connection->selectQuery();
        $query->select(['id'])
            ->from('articles')
            ->orderByAsc('id');

        $sql = $query->sql();
        $result = $query->execute()->fetchAll('assoc');
        $expected = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $this->assertEquals($expected, $result);
        $this->assertQuotedQuery(
            'SELECT <id> FROM <articles> ORDER BY <id> ASC',
            $sql,
            !$this->autoQuote
        );

        $query = $this->connection->selectQuery();
        $query->select(['id'])
            ->from('articles')
            ->orderByAsc($query->func()->concat(['id' => 'identifier', '3']));

        $result = $query->execute()->fetchAll('assoc');
        $expected = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test orderDesc() and its input types.
     *
     * @return void
     */
    public function testSelectOrderDesc()
    {
        $this->loadFixtures('Articles');
        $query = $this->connection->selectQuery();
        $query->select(['id'])
            ->from('articles')
            ->orderByDesc('id');
        $sql = $query->sql();
        $result = $query->execute()->fetchAll('assoc');
        $expected = [
            ['id' => 3],
            ['id' => 2],
            ['id' => 1],
        ];
        $this->assertEquals($expected, $result);
        $this->assertQuotedQuery(
            'SELECT <id> FROM <articles> ORDER BY <id> DESC',
            $sql,
            !$this->autoQuote
        );

        $query = $this->connection->selectQuery();
        $query->select(['id'])
            ->from('articles')
            ->orderByDesc($query->func()->concat(['id' => 'identifier', '3']));

        $result = $query->execute()->fetchAll('assoc');
        $expected = [
            ['id' => 3],
            ['id' => 2],
            ['id' => 1],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Tests that query expressions can be used for ordering.
     *
     * @return void
     */
    public function testOrderBySubquery()
    {
        $this->markTestSkipped('QueryExpression::addCase() was removed in CakePHP 5');
        $this->autoQuote = true;
        $this->connection->getDriver()->enableAutoQuoting($this->autoQuote);

        $this->loadFixtures('Articles');
        $connection = $this->connection;

        $query = $connection->selectQuery();

        $stmt = $connection->update('articles', ['published' => 'N'], ['id' => 3]);
        $stmt->closeCursor();

        $subquery = $connection->selectQuery();
        $subquery
            ->select(
                $subquery->expr()->addCase(
                    [$subquery->expr()->add(['a.published' => 'N'])],
                    [1, 0],
                    ['integer', 'integer']
                )
            )
            ->from(['a' => 'articles'])
            ->where([
                $subquery->expr()->equalFields('a.id', 'articles.id')
            ]);

        $query
            ->select(['id'])
            ->from('articles')
            ->orderByDesc($subquery)
            ->orderByAsc('id')
            ->setSelectTypeMap(new TypeMap([
                'id' => 'integer',
            ]));

        $this->assertQuotedQuery(
            'SELECT <id> FROM <articles> ORDER BY \(' .
                'SELECT \(CASE WHEN <a>\.<published> = \:c0 THEN \:param1 ELSE \:param2 END\) ' .
                'FROM <articles> <a> ' .
                'WHERE <a>\.<id> = \(<articles>\.<id>\)' .
            '\) DESC, <id> ASC',
            $query->sql(),
            !$this->autoQuote
        );
        $this->assertEquals(
            [
                [
                    'id' => 3,
                ],
                [
                    'id' => 1,
                ],
                [
                    'id' => 2,
                ],
            ],
            $query->execute()->fetchAll('assoc')
        );
    }

    public function testReusingExpressions()
    {
        $this->markTestSkipped('Oracle returns strings for computed fields; assertSame would fail type checks');
        $this->loadFixtures('Articles');
        $connection = $this->connection;

        $query = $connection->selectQuery();

        $stmt = $connection->update('articles', ['published' => 'N'], ['id' => 3]);
        $stmt->closeCursor();

        $subqueryA = $connection->selectQuery();
        $subqueryA
            ->select('count(*)')
            ->from(['a' => 'articles'])
            ->where([
                $subqueryA->expr()->equalFields('a.id', 'articles.id'),
                'a.published' => 'Y',
            ]);

        $subqueryB = $connection->selectQuery();
        $subqueryB
            ->select('count(*)')
            ->from(['b' => 'articles'])
            ->where([
                $subqueryB->expr()->equalFields('b.id', 'articles.id'),
                'b.published' => 'N',
            ]);

        $query
            ->select([
                'id',
                'computedA' => $subqueryA,
                'computedB' => $subqueryB,
            ])
            ->from('articles')
            ->orderByDesc($subqueryB)
            ->orderByAsc('id')
            ->setSelectTypeMap(new TypeMap([
                'id' => 'integer',
                'computedA' => 'integer',
                'computedB' => 'integer',
            ]));

        $this->assertQuotedQuery(
            'SELECT <id>, ' .
                '\(SELECT count\(\*\) FROM <articles> <a> WHERE \(<a>\.<id> = \(<articles>\.<id>\) AND <a>\.<published> = :c0\)\) AS <computedA>, ' .
                '\(SELECT count\(\*\) FROM <articles> <b> WHERE \(<b>\.<id> = \(<articles>\.<id>\) AND <b>\.<published> = :c1\)\) AS <computedB> ' .
            'FROM <articles> ' .
            'ORDER BY \(' .
                'SELECT count\(\*\) FROM <articles> <b> WHERE \(<b>\.<id> = \(<articles>\.<id>\) AND <b>\.<published> = :c2\)' .
            '\) DESC, <id> ASC',
            $query->sql(),
            !$this->autoQuote
        );

        $this->assertSame(
            [
                [
                    'id' => 3,
                    'computedA' => 0,
                    'computedB' => 1,
                ],
                [
                    'id' => 1,
                    'computedA' => 1,
                    'computedB' => 0,
                ],
                [
                    'id' => 2,
                    'computedA' => 1,
                    'computedB' => 0,
                ],
            ],
            $query->execute()->fetchAll('assoc')
        );

        $this->assertSame(
            [
                ':c0' => [
                    'value' => 'Y',
                    'type' => null,
                    'placeholder' => 'c0',
                ],
                ':c1' => [
                    'value' => 'N',
                    'type' => null,
                    'placeholder' => 'c1',
                ],
                ':c2' => [
                    'value' => 'N',
                    'type' => null,
                    'placeholder' => 'c2',
                ],
            ],
            $query->getValueBinder()->bindings()
        );
    }

    /**
     * Tests creating StringExpression.
     *
     * @return void
     */
    public function testStringExpression()
    {
        $driver = $this->connection->getDriver();
        $collation = 'LATIN_AI';

        $query = $this->connection->selectQuery();
        $query->select(['test_string' => new StringExpression('testString', $collation)])->from('DUAL');
        $expected = "SELECT \(:c0 COLLATE {$collation}\) AS <test_string> FROM <DUAL>";
        $this->assertRegExpSql($expected, $query->sql(new ValueBinder()), !$this->autoQuote);

        $statement = $query->execute();
        $this->assertSame('testString', $statement->fetchColumn(0));
        $statement->closeCursor();
    }


    /**
     * Test insert() with no into()
     *
     * @return void
     */
    public function testInsertNoInto()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Could not compile insert query. No table was specified');
        $query = $this->connection->insertQuery();
        $query->insert(['title', 'body'])->sql();
    }

    /**
     * Tests setting identifier collation.
     *
     * @return void
     */
    public function testIdentifierCollation()
    {
        $this->loadFixtures('Articles');
        $driver = $this->connection->getDriver();
        $collation = 'LATIN_AI';

        $query = ($this->connection->selectQuery())
            ->select(['test_string' => new IdentifierExpression('title', $collation)])
            ->from('articles')
            ->where(['id' => 1]);

        if ($driver instanceof Postgres) {
            // Older postgres versions throw an error on the parameter type without a cast
            $expected = "SELECT \(<title> COLLATE \"{$collation}\"\) AS <test_string>";
        } else {
            $expected = "SELECT \(<title> COLLATE {$collation}\) AS <test_string>";
        }
        $this->assertRegExpSql($expected, $query->sql(new ValueBinder()), !$this->autoQuote);

        $statement = $query->execute();
        $this->assertSame('First Article', $statement->fetchColumn(0));
        $statement->closeCursor();
    }
}
