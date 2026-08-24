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

use Cake\Database\Expression\FunctionExpression;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Entity;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Table;
use Cake\Test\TestCase\ORM\TableTest as CakeTableTest;
use Cake\Validation\Validator;
use TestApp\Model\Entity\ProtectedEntity;

/**
 * Tests Table class
 */
class TableTest extends CakeTableTest
{
    protected array $fixtures = [
        'core.Articles',
        'core.Tags',
        //'core.ArticlesTags',
        'plugin.CakeDC/OracleDriver.ArticlesTags',
        'core.Authors',
        'core.Categories',
        'core.Comments',
        'core.Sections',
        'core.SectionsMembers',
        'core.Members',
        'core.PolymorphicTagged',
        'core.SiteArticles',
        'core.Users',
    ];

    /**
     * Tests find('list')
     *
     * @return void
     */
    public function testFindListNoHydration(): void
    {
        $table = new Table([
            'table' => 'users',
            'connection' => $this->connection,
        ]);
        $table->setDisplayField('username');

        $query = $table
            ->find('list')
            ->enableHydration(false)
            ->orderBy('id');
        $expected = [
            1 => 'mariano',
            2 => 'nate',
            3 => 'larry',
            4 => 'garrett',
        ];
        $this->assertSame($expected, $query->toArray());

        $query = $table->find('list', keyField: 'id', valueField: 'username')
                       ->enableHydration(false)
                       ->orderBy('id');
        $expected = [
            1 => 'mariano',
            2 => 'nate',
            3 => 'larry',
            4 => 'garrett',
        ];
        $this->assertSame($expected, $query->toArray());

        $query = $table->find('list', groupField: 'odd')
           ->select([
               'id',
               'username',
               'odd' => new FunctionExpression('MOD', [new IdentifierExpression('id'), 2]),
           ])
           ->enableHydration(false)
           ->orderBy('id');
        $expected = [
            1 => [
                1 => 'mariano',
                3 => 'larry',
            ],
            0 => [
                2 => 'nate',
                4 => 'garrett',
            ],
        ];
        $this->assertSame($expected, $query->toArray());
    }

    /**
     * Tests find('list') with hydrated records
     *
     * @return void
     */
    public function testFindListHydrated(): void
    {
        $table = new Table([
            'table' => 'users',
            'connection' => $this->connection,
        ]);
        $table->setDisplayField('username');

        $query = $table->find('list', keyField: 'id', valueField: 'username')
                       ->orderBy('id');
        $expected = [
            1 => 'mariano',
            2 => 'nate',
            3 => 'larry',
            4 => 'garrett',
        ];
        $this->assertSame($expected, $query->toArray());

        $query = $table->find('list', groupField: 'odd')
           ->select([
               'id',
               'username',
               'odd' => new FunctionExpression('MOD', [new IdentifierExpression('id'), 2]),
           ])
           ->enableHydration(true)
           ->orderBy('id');
        $expected = [
            1 => [
                1 => 'mariano',
                3 => 'larry',
            ],
            0 => [
                2 => 'nate',
                4 => 'garrett',
            ],
        ];
        $this->assertSame($expected, $query->toArray());
    }

    /**
     * Test that the associated entities are unlinked and deleted when they have a not nullable foreign key
     *
     * @return void
     */
    public function testSaveReplaceSaveStrategyAdding(): void
    {
        $articles = new Table([
                'table' => 'articles',
                'alias' => 'Articles',
                'connection' => $this->connection,
                'entityClass' => Entity::class,
            ]);

        $articles->hasMany('Comments', ['saveStrategy' => 'replace']);

        $article = $articles->newEntity([
            'title' => 'Bakeries are sky rocketing',
            'body' => 'All because of cake',
            'comments' => [
                [
                    'user_id' => 1,
                    'comment' => 'That is true!',
                ],
                [
                    'user_id' => 2,
                    'comment' => 'Of course',
                ],
            ],
        ], ['associated' => ['Comments']]);

        $article = $articles->save($article, ['associated' => ['Comments']]);

        $commentId = $article->comments[0]->id;
        $sizeComments = count($article->comments);
        $articleId = $article->id;

        $this->assertEquals($sizeComments, $articles->Comments->find('all')
                                                              ->where(['article_id' => $article->id])
                                                              ->count());
        $this->assertTrue($articles->Comments->exists(['id' => $commentId]));

        unset($article->comments[0]);
        $article->comments[] = $articles->Comments->newEntity([
            'user_id' => 1,
            'comment' => 'new comment',
        ]);

        $article->setDirty('comments', true);
        $article = $articles->save($article, ['associated' => ['Comments']]);

        $this->assertEquals($sizeComments, $articles->Comments->find('all')
                                                              ->where(['article_id' => $article->id])
                                                              ->count());
        $this->assertFalse($articles->Comments->exists(['id' => $commentId]));
        $this->assertTrue($articles->Comments->exists([
            'to_char(comment)' => 'new comment',
            'article_id' => $articleId,
        ]));
    }

    /**
     * Test that findOrCreate cannot accidentally bypass required validation.
     *
     * @return void
     */
    public function testFindOrCreatePartialValidation(): void
    {
        $articles = $this->getTableLocator()->get('Articles');
        $articles->setEntityClass(ProtectedEntity::class);

        $validator = new Validator();
        $validator->notBlank('title')->requirePresence('title', 'create');
        $validator->notBlank('body')->requirePresence('body', 'create');
        $articles->setValidator('default', $validator);

        $this->expectException(PersistenceFailedException::class);
        $this->expectExceptionMessage(
            'Entity findOrCreate failure. ' .
            'Found the following errors (body._required: "This field is required").',
        );

        $articles->findOrCreate(['title' => 'test']);
    }

    public function testSubqueryJoinClause(): void
    {
        $subquery = $this->getTableLocator()->get('Articles')->subquery()
            ->select(['author_id']);

        $query = $this->getTableLocator()->get('Authors')->find();
        $query
            ->select([
                'Authors.id',
                'total_articles' => $query->func()->count(new IdentifierExpression('articles.author_id')),
            ])
            ->leftJoin(['articles' => $subquery], ['articles.author_id' => new IdentifierExpression('Authors.id')])
            ->groupBy(['Authors.id'])
            ->orderBy(['Authors.id' => 'ASC']);

        $results = $query->all()->toList();
        $this->assertEquals(1, $results[0]->id);
        $this->assertEquals(2, $results[0]->total_articles);
    }

    public function testUpdateExpression(): void
    {
        $table = new Table([
            'table' => 'counter_cache_users',
            'connection' => $this->connection,
        ]);
        $entity = new Entity([
            'name' => 'test',
            'post_count' => 0,
            'comment_count' => 0,
            'posts_published' => 0,
        ]);
        $table->save($entity);
        $expression = new QueryExpression(['"post_count" = "post_count" + 1']);
        $result = $table->updateAll([$expression], ['id' => $entity->id]);
        $this->assertNotEmpty($result);
    }

    public function testPolymorphicBelongsToManySave(): void
    {
        $this->skipIf(ConnectionManager::get('test')->getDriver()->getMaxAliasLength() < 31);

        $articles = $this->getTableLocator()->get('Articles');
        $articles->Tags->setThrough('PolymorphicTagged')
            ->setForeignKey('foreign_key')
            ->setConditions(['PolymorphicTagged.foreign_model' => 'Articles'])
            ->setSort(['PolymorphicTagged.position' => 'ASC']);

        $entity = $articles->get(1, contain: ['Tags']);
        $data = [
            'id' => 1,
            'tags' => [
                ['id' => 1, '_joinData' => ['id' => 2, 'foreign_model' => 'Articles', 'position' => 2]],
                ['id' => 2, '_joinData' => ['foreign_model' => 'Articles', 'position' => 1]],
            ],
        ];
        $entity = $articles->patchEntity($entity, $data, ['associated' => ['Tags._joinData']]);
        $articles->save($entity);

        $result = $this->getTableLocator()->get('PolymorphicTagged')
            ->find('all')
            ->enableHydration(false)
            ->orderBy(['id' => 'ASC'])
            ->toArray();

        $this->assertCount(3, $result);

        $postRow = array_values(array_filter($result, fn($r): bool => $r['foreign_model'] === 'Posts'));
        $articleRows = array_values(array_filter($result, fn($r): bool => $r['foreign_model'] === 'Articles'));
        usort($articleRows, fn($a, $b): int => $a['tag_id'] <=> $b['tag_id']);

        $this->assertSame('Posts', $postRow[0]['foreign_model']);
        $this->assertSame(1, $articleRows[0]['tag_id']);
        $this->assertSame(2, $articleRows[0]['position']);
        $this->assertSame(2, $articleRows[1]['tag_id']);
        $this->assertSame(1, $articleRows[1]['position']);
    }
}
