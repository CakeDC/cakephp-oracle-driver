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

use Cake\Database\Schema\TableSchema;
use Cake\TestSuite\Fixture\TestFixture;

/**
 * Base fixture that builds schema from legacy field definitions.
 */
abstract class SchemaAwareTestFixture extends TestFixture
{
    /**
     * @return void
     */
    public function init(): void
    {
        $this->_schema = $this->buildSchema($this->table, $this->getFieldDefinitions());
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    abstract protected function getFieldDefinitions(): array;

    /**
     * @param string $tableName Table name.
     * @param array<string, array<string, mixed>|string> $fields Field definitions.
     * @return \Cake\Database\Schema\TableSchema
     */
    protected function buildSchema(string $tableName, array $fields): TableSchema
    {
        $columns = [];
        $constraints = [];
        foreach ($fields as $name => $field) {
            if ($name === '_constraints') {
                $constraints = $field;
                continue;
            }
            if (is_string($field)) {
                $columns[$name] = ['type' => $field];
                continue;
            }
            $columns[$name] = $field;
        }

        $schema = new TableSchema($tableName, $columns);
        foreach ($constraints as $key => $constraint) {
            $schema->addConstraint($key, $constraint);
        }

        return $schema;
    }
}
