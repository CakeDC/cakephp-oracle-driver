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
 * Articles tags junction fixture for Oracle driver tests.
 */
class ArticlesTagsFixture extends SchemaAwareTestFixture
{
    public string $table = 'articles_tags';

    public array $records = [
        ['article_id' => 1, 'tag_id' => 1],
        ['article_id' => 1, 'tag_id' => 2],
        ['article_id' => 2, 'tag_id' => 1],
        ['article_id' => 2, 'tag_id' => 3],
    ];

    /**
     * @inheritDoc
     */
    protected function getFieldDefinitions(): array
    {
        return [
            'article_id' => ['type' => 'integer', 'null' => false],
            'tag_id' => ['type' => 'integer', 'null' => false],
            '_constraints' => [
                'unique_tag' => ['type' => 'primary', 'columns' => ['article_id', 'tag_id']],
            ],
        ];
    }
}
