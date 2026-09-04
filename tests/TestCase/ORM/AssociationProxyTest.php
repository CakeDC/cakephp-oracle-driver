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

use Cake\Test\TestCase\ORM\AssociationProxyTest as CakeAssociationProxyTest;

/**
 * Tests AssociationProxy class
 */
class AssociationProxyTest extends CakeAssociationProxyTest
{
    /**
     * Tests that the proxied updateAll will preserve conditions set for the association
     *
     * @return void
     */
    public function testUpdateAllFromAssociation(): void
    {
        $articles = $this->getTableLocator()->get('articles');
        $comments = $this->getTableLocator()->get('comments');
        $articles->hasMany('comments', ['conditions' => ['published' => 'Y']]);
        $articles->comments->updateAll(['comment' => 'changed'], ['article_id' => 1]);

        $changed = $comments
            ->find()
            ->where(['to_char(comment)' => 'changed'])
            ->count();
        $this->assertEquals(3, $changed);
    }

    /**
     * Tests that the proxied updateAll uses the association scope.
     *
     * Overridden to define the association inline instead of relying on
     * `TestApp` table classes, so the test does not depend on the
     * `App.namespace` class resolution.
     *
     * @return void
     */
    public function testUpdateAllFromAssociationFinder(): void
    {
        $articles = $this->getTableLocator()->get('articles');
        $authors = $this->getTableLocator()->get('authors');
        // Exclude a record from the published scope.
        $articles->updateAll(['published' => 'N'], ['id' => 1]);

        $authors->hasMany('Articles', ['conditions' => ['Articles.published' => 'Y']]);
        $authors->Articles->updateAll(['published' => '?'], '1=1');

        $missed = $articles->find()->where(['published' => 'Y'])->count();
        $this->assertSame(0, $missed);

        $remaining = $articles->find()->where(['published' => 'N'])->count();
        $this->assertSame(1, $remaining);
    }

    /**
     * Tests that the proxied deleteAll uses the association scope.
     *
     * Overridden to define the association inline instead of relying on
     * `TestApp` table classes, so the test does not depend on the
     * `App.namespace` class resolution.
     *
     * @return void
     */
    public function testDeleteAllFromAssociationFinder(): void
    {
        $articles = $this->getTableLocator()->get('articles');
        $authors = $this->getTableLocator()->get('authors');
        // Exclude a record from the published scope.
        $articles->updateAll(['published' => 'N'], ['id' => 1]);

        $authors->hasMany('Articles', ['conditions' => ['Articles.published' => 'Y']]);
        $authors->Articles->deleteAll('1=1');

        $remaining = $articles->find()->all();
        $this->assertCount(1, $remaining);
        $this->assertSame(['N'], $remaining->extract('published')->toList());
    }
}
