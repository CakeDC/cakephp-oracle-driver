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
namespace CakeDC\OracleDriver\Test\TestCase\ORM;

use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\Test\TestCase\ORM\Query\QueryRegressionTest as CakeQueryRegressionTest;
use TestApp\Model\Table\ArticlesTable;
use TestApp\Model\Table\TagsTable;

/**
 * Tests QueryRegression class
 */
class QueryRegressionTest extends CakeQueryRegressionTest
{
    protected array $fixtures = [
        'core.Articles',
        'core.Tags',
        'plugin.CakeDC/OracleDriver.ArticlesTags',
        'core.Authors',
        'core.AuthorsTags',
        'core.Comments',
        'core.FeaturedTags',
        'core.SpecialTags',
        'core.TagsTranslations',
        'core.Translates',
        'core.Users',
    ];

    /**
     * Test expression based ordering with unions.
     */
    public function testComplexOrderWithUnion(): void
    {
        $table = $this->getTableLocator()->get('Comments');
        $query = $table->find();
        $inner = $table->find()
            ->select(['content' => 'to_char(comment)'])
            ->where(['id >' => 3]);
        $inner2 = $table->find()
            ->select(['content' => 'to_char(comment)'])
            ->where(['id <' => 3]);

        $order = $query->func()
            ->concat(['content' => 'identifier', 'test']);

        $query->select(['inside.content'])
            ->from(['inside' => $inner->unionAll($inner2)])
            ->orderByAsc($order);

        $results = $query->toArray();
        $this->assertCount(5, $results);
    }

    /**
     * Test that save() works with entities containing expressions as properties.
     */
    public function testSaveWithExpressionProperty(): void
    {
        $articles = $this->getTableLocator()->get('Articles');
        $article = $articles->newEntity([]);
        $article->title = new QueryExpression("SELECT 'jose' from DUAL");
        $this->assertSame($article, $articles->save($article));
    }

    /**
     * Such syntax is not supported and leads to ORA-00937.
     */
    public function testSubqueryInSelectExpression(): void
    {
        $this->markTestSkipped();
    }

    /**
     * Tests that subqueries can be used with function expressions.
     */
    public function testFunctionExpressionWithSubquery(): void
    {
        $table = $this->getTableLocator()->get('Articles');

        $query = $table
            ->find()
            ->select(fn(SelectQuery $q): array => [
                'value' => $q
                    ->func()
                    ->ABS([
                        $table
                            ->getConnection()
                            ->selectQuery(-1),
                    ])
                    ->setReturnType('integer'),
            ]);

        $result = $query->first()->get('value');
        $this->assertEquals(1, $result);
    }

    /**
     * Tests that subqueries can be used with multi argument function expressions.
     */
    public function testMultiArgumentFunctionExpressionWithSubquery(): void
    {
        $table = $this->getTableLocator()->get('Articles');

        $query = $table
            ->find()
            ->select(fn(SelectQuery $q): array => [
                'value' => $q
                    ->func()
                    ->ROUND(
                        [
                            $table
                                ->getConnection()
                                ->selectQuery(1.23456),
                            2,
                        ],
                        [null, 'integer'],
                    )
                    ->setReturnType('float'),
            ]);

        $result = $query->first()->get('value');
        $this->assertEquals(1.23, $result);
    }

    /**
     * We can use only union all with queries with clob fields.
     *
     * @see https://asktom.oracle.com/pls/apex/f?p=100:11:0::::P11_QUESTION_ID:498299691850
     */
    public function testCountWithUnionQuery(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $query = $table->find()
            ->where(['id' => 1]);
        $query2 = $table->find()
            ->where(['id' => 2]);
        $query->unionAll($query2);
        $this->assertEquals(2, $query->count());

        $fields = [
            'id',
            'author_id',
            'title',
            'body' => 'to_char(body)',
            'published',
        ];
        $query = $table->find()
            ->select($fields)
            ->where(['id' => 1]);
        $query2 = $table->find()
            ->select($fields)
            ->where(['id' => 2]);
        $query->union($query2);
        $this->assertEquals(2, $query->count());
    }

    /**
     * Tests that EagerLoader does not try to create queries for associations having no
     * keys to compare against.
     */
    public function testEagerLoadingFromEmptyResults(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->belongsToMany('ArticlesTags');

        $results = $table->find()->where(['id >' => 100])->contain('ArticlesTags')->toArray();
        $this->assertEmpty($results);
    }

    /**
     * Tests that getting the count of a query with bind is correct.
     *
     * @see https://github.com/cakephp/cakephp/issues/8466
     */
    public function testCountWithBind(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $query = $table->find()
            ->select(['title', 'id'])
            ->where('"title" LIKE :val')
            ->groupBy(['id', 'title'])
            ->bind(':val', '%Second%');
        $count = $query->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Tests that bind in subqueries works.
     */
    public function testSubqueryBind(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $sub = $table->find()
            ->select(['id'])
            ->where('"title" LIKE :val')
            ->bind(':val', 'Second %');

        $query = $table
            ->find()
            ->select(['title'])
            ->where(['id NOT IN' => $sub]);
        $result = $query->toArray();
        $this->assertCount(2, $result);
        $this->assertSame('First Article', $result[0]->title);
        $this->assertSame('Third Article', $result[1]->title);
    }

    /**
     * Test selecting with aliased aggregates and identifier quoting
     * does not emit notice errors.
     *
     * @see https://github.com/cakephp/cakephp/issues/12766
     */
    public function testAliasedAggregateFieldTypeConversionSafe(): void
    {
        $articles = $this->getTableLocator()->get('Articles');

        $driver = $articles->getConnection()->getDriver();
        $restore = $driver->isAutoQuotingEnabled();

        $driver->enableAutoQuoting(true);
        $query = $articles->find();
        $query->select([
            'sumUsers' => $articles->find()->func()->sum(new IdentifierExpression('author_id')),
        ]);
        $driver->enableAutoQuoting($restore);

        $result = $query->execute()->fetchAll('assoc');
        $this->assertArrayHasKey('sumUsers', $result[0]);
    }

    /**
     * Test that the typemaps used in function expressions create the correct results.
     */
    public function testTypemapInFunctions2(): void
    {
        $table = $this->getTableLocator()->get('Comments');
        $query = $table->find();
        $query->select([
            'max' => $query->func()->max(new IdentifierExpression('created'), ['datetime']),
        ]);
        $result = $query->all()->first();
        $this->assertEquals(new DateTime('2007-03-18 10:55:23'), $result['max']);
    }

    /**
     * Tests that correlated subqueries can be used with function expressions.
     */
    public function testFunctionExpressionWithCorrelatedSubquery(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->belongsTo('Authors');

        $query = $table
            ->find()
            ->select(fn(SelectQuery $q): array => [
                'value' => $q->func()->UPPER([
                    $table
                        ->getAssociation('Authors')
                        ->find()
                        ->select(['Authors.name'])
                        ->where(fn(QueryExpression $exp) => $exp->equalFields('Authors.id', 'Articles.author_id')),
                ]),
            ]);

        $result = $query->first()->get('value');
        $this->assertEquals('MARIANO', $result);
    }

    public function testBelongsToManyDeepSave2(): void
    {
        $articles = $this->getTableLocator()->get('Articles');
        $articles->belongsToMany('Highlights', [
            'className' => TagsTable::class,
            'targetForeignKey' => 'tag_id',
            'through' => 'SpecialTags',
        ]);
        $articles->Highlights->hasMany('TopArticles', [
            'className' => ArticlesTable::class,
            'foreignKey' => 'author_id',
            'sort' => ['TopArticles.id' => 'ASC'],
        ]);
        $entity = $articles->get(2, ...['contain' => ['Highlights']]);

        $data = [
            'highlights' => [
                [
                    'name' => 'New Special Tag',
                    '_joinData' => [
                        'highlighted' => true,
                        'highlighted_time' => '2014-06-01 10:10:00',
                    ],
                    'top_articles' => [
                        ['title' => 'First top article'],
                        ['title' => 'Second top article'],
                    ],
                ],
            ],
        ];
        $options = ['associated' => ['Highlights._joinData', 'Highlights.TopArticles']];
        $entity = $articles->patchEntity($entity, $data, $options);
        $articles->save($entity, $options);
        $entity = $articles->get(2, ...[
            'contain' => ['Highlights.TopArticles' => ['sort' => ['TopArticles.id' => 'ASC']]],
        ]);
        $highlights = $entity->highlights[0];
        $this->assertSame('First top article', $highlights->top_articles[0]->title);
        $this->assertSame('Second top article', $highlights->top_articles[1]->title);
        $this->assertEquals(
            new DateTime('2014-06-01 10:10:00'),
            $highlights->_joinData->highlighted_time,
        );
    }

    public function testTypemapInFunctions3(): void
    {
        $this->markTestSkipped('Oracle requires quoted identifiers; unquoted "ID" causes ORA-00904.');
    }

    public function testAssociationSubQueryNoOffset(): void
    {
        $this->skipIf(ConnectionManager::get('test')->getDriver()->getMaxAliasLength() < 31);
        parent::testAssociationSubQueryNoOffset();
    }
}
