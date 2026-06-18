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
 * Authors fixture for Oracle driver tests.
 */
class AuthorsFixture extends SchemaAwareTestFixture
{
    public string $table = 'authors';

    public array $records = [
        ['name' => 'evgeny'],
        ['name' => 'mark'],
        ['name' => 'larry'],
    ];

    /**
     * @inheritDoc
     */
    protected function getFieldDefinitions(): array
    {
        return [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string', 'default' => null],
            '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
        ];
    }
}
