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
 * Tags fixture for Oracle driver tests.
 */
class TagsFixture extends SchemaAwareTestFixture
{
    public string $table = 'tags';

    public array $records = [
        ['name' => 'tag1'],
        ['name' => 'tag2'],
        ['name' => 'tag3'],
    ];

    /**
     * @inheritDoc
     */
    protected function getFieldDefinitions(): array
    {
        return [
            'id' => ['type' => 'integer', 'null' => false],
            'name' => ['type' => 'string', 'null' => false],
            '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
        ];
    }
}
