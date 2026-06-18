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

namespace CakeDC\OracleDriver\Test\Fixture;

/**
 * Articles fixture for Oracle driver tests.
 */
class ArticlesFixture extends SchemaAwareTestFixture
{
    public string $table = 'articles';

    public array $records = [
        ['author_id' => 1, 'title' => 'First Article', 'body' => 'First Article Body', 'published' => 'Y'],
        ['author_id' => 3, 'title' => 'Second Article', 'body' => 'Second Article Body', 'published' => 'Y'],
        ['author_id' => 1, 'title' => 'Third Article', 'body' => 'Third Article Body', 'published' => 'Y'],
    ];

    /**
     * @inheritDoc
     */
    protected function getFieldDefinitions(): array
    {
        return [
            'id' => ['type' => 'integer'],
            'author_id' => ['type' => 'integer', 'null' => true],
            'title' => ['type' => 'string', 'null' => true],
            'body' => 'text',
            'published' => ['type' => 'string', 'length' => 1, 'default' => 'N'],
            '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
        ];
    }
}
