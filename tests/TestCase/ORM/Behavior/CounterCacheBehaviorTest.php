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

namespace CakeDC\OracleDriver\Test\TestCase\ORM\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Test\TestCase\ORM\Behavior\CounterCacheBehaviorTest as CakeCounterCacheBehaviorTest;

/**
 * Tests CounterCacheBehavior class
 */
class CounterCacheBehaviorTest extends CakeCounterCacheBehaviorTest
{
    /**
     * Testing counter cache with lambda returning a subquery
     *
     * @return void
     */
    public function testLambdaSubquery(): void
    {
        $this->post->belongsTo('Users');

        $this->post->addBehavior('CounterCache', [
            'Users' => [
                'posts_published' => function (EventInterface $event, EntityInterface $entity, Table $table) {
                    $query = new SelectQuery($table);

                    return $query->select(4)->from('DUAL');
                },
            ],
        ]);

        $before = $this->_getUser();
        $entity = $this->_getEntity();
        $this->post->save($entity);
        $after = $this->_getUser();

        $this->assertEquals(1, $before->get('posts_published'));
        $this->assertEquals(4, $after->get('posts_published'));
    }
}
