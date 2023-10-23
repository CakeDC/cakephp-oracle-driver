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
namespace CakeDC\OracleDriver\Database\Schema;

use Cake\Database\Schema\SchemaDialect;
use Cake\Database\Schema\TableSchema;
use Cake\Utility\Hash;
use CakeDC\OracleDriver\Database\Exception\UnallowedDataTypeException;

/**
 * Schema management/reflection features for Oracle.
 */
class OracleSchema extends SchemaDialect
{
    protected $_constraints = [];

    protected $integerTypes = [
        TableSchema::TYPE_INTEGER => 11,
        TableSchema::TYPE_SMALLINTEGER => 5,
        TableSchema::TYPE_TINYINTEGER => 5,
        TableSchema::TYPE_BIGINTEGER => 20,
    ];

    /**
     * Generate the SQL to list the methods.
     *
     * @param array $config The connection configuration to use for

     *    getting tables from.
     * @return array An array of (sql, params) to execute.
     */
    public function listMethodsSql($config)
    {
        if (empty($config['schema'])) {
            $table = 'user_procedures';
            $useOwner = false;
            $params = [];
        } else {
            $table = 'all_procedures';
            $useOwner = true;
            $params = [':ownerParam' => strtoupper($config['schema'])];
            $ownerCondition = 'AND OWNER = :ownerParam';
        }
        $objectNameField = $this->_transformFieldCase("OBJECT_NAME");
        $procedureName = $this->_transformFieldCase("PROCEDURE_NAME");
        $objectCondition = '';
        if (!empty($config['objectName'])) {
            $objectName = $config['objectName'];

            $items = explode('.', $objectName);
            $itemsCount = count($items);
            if ($itemsCount === 3) {
                [$schema, $package, $object] = explode('.', $objectName);
            } elseif ($itemsCount === 2) {
                [$package, $object] = explode('.', $objectName);
            } else {
                $schema = $package = null;
                $object = $objectName;
            }
            $ownerCondition = '';
            $params = [
                ':objectParam' => $object,
            ];
            if (empty($schema) && empty($package)) {
                $table = 'user_procedures';
                $useOwner = false;
                $objectCondition = " AND $objectNameField = :objectParam ";
            } else {
                $table = 'all_procedures';
                $useOwner = true;
                if (!empty($package) && !empty($schema)) {
                    $ownerCondition = " AND OWNER = :ownerParam AND $objectNameField = :packageParam ";
                    $params = [
                        ':objectParam' => $object,
                        ':packageParam' => $package,
                        ':ownerParam' => $schema,
                    ];
                    $objectCondition = " AND $procedureName = :objectParam ";
                } elseif (!empty($package)) {
                    $ownerCondition = " AND (OWNER = :packageParam OR $objectNameField = :packageParam) ";
                    $params = [
                        ':objectParam' => $object,
                        ':packageParam' => $package,
                    ];
                    $objectCondition = " AND $procedureName = :objectParam ";
                }
            }
        }
        $procedureName = $this->_transformFieldCase("PROCEDURE_NAME");
        $sql = "SELECT $objectNameField as object, $procedureName as name, OBJECT_TYPE FROM $table
WHERE 1=1 " . ($useOwner ? $ownerCondition : '') . $objectCondition . " ORDER BY object, name";

        return [
            $sql,
            $params,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function listTablesSql(array $config): array
    {
        if (empty($config['schema'])) {
            $table = 'user_tables';
            $useOwner = false;
            $params = [];
        } else {
            $table = 'all_tables';
            $useOwner = true;
            $params = [':ownerParam' => strtoupper($config['schema'])];
        }
        $tableName = $this->_transformFieldCase("TABLE_NAME");
        $sql = "SELECT $tableName as name FROM $table " . ($useOwner ? 'WHERE owner = :ownerParam' : '') . " ORDER BY name";

        return [
            $sql,
            $params,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function describeColumnSql(string $tableName, array $config): array
    {
        [$schema, $table] = $this->tableSplit($tableName, $config);
        if (empty($schema)) {
            $columnsTable = 'user_tab_columns';
            $commentsTable = 'user_col_comments';
            $useOwner = false;
            $params = [':tableParam' => $table];
        } else {
            $columnsTable = 'all_tab_columns';
            $commentsTable = 'all_col_comments';
            $useOwner = true;
            $params = [
                ':tableParam' => $table,
                ':ownerParam' => $schema,
            ];
        }
        $sql = "SELECT
                    utc.table_name as \"table\",
                    utc.column_name AS name,
                    utc.data_type AS type,
                    utc.data_length AS char_length,
                    utc.data_precision,
                    utc.data_scale,
                    utc.nullable AS \"null\",
                    utc.data_default AS \"default\",
                    ucc.comments AS \"comment\",
                    utc.column_id AS ordinal_position
                FROM $columnsTable utc
                JOIN $commentsTable ucc ON (
                    utc.table_name = ucc.table_name
                    AND utc.column_name = ucc.column_name
                " . ($useOwner ? 'AND utc.OWNER = ucc.OWNER' : '') . "
                )
                WHERE UPPER(utc.table_name) = :tableParam
                " . ($useOwner ? 'AND utc.OWNER = :ownerParam' : '') . "
                ORDER BY utc.column_id";

        return [
            $sql,
            $params,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function convertColumnDescription(TableSchema $schema, array $row): void
    {
        $row = array_change_key_case($row);
        switch ($row['type']) {
            case 'DATE':
                $field = [
                    'type' => TableSchema::TYPE_DATETIME,
                    'length' => null,
                ];
                break;
            case 'TIMESTAMP':
            case 'TIMESTAMP(6)':
            case 'TIMESTAMP(9)':
                $field = [
                    'type' => TableSchema::TYPE_TIMESTAMP,
                    'length' => null,
                ];
                break;
            case 'NUMBER':
            case 'INTEGER':
            case 'PLS_INTEGER':
            case 'BINARY_INTEGER':
                $field = $this->_numberFieldDefinition($row);
                break;
            case 'FLOAT':
            case 'BINARY_FLOAT':
            case 'BINARY_DOUBLE':
                $field = [
                    'type' => TableSchema::TYPE_FLOAT,
                    'length' => $row['data_precision'],
                ];
                break;
            case 'NCHAR':
            case 'NVARCHAR2':
            case 'CHAR':
            case 'VARCHAR2':
            case 'LONG':
            case 'ROWID':
            case 'UROWID':
                $length = $row['char_length'];
                if ($length == 36) {
                    $field = [
                        'type' => TableSchema::TYPE_UUID,
                        'length' => null,
                    ];
                } else {
                    $field = [
                        'type' => TableSchema::TYPE_STRING,
                        'length' => $length,
                    ];
                }
                break;
            case 'NCLOB':
            case 'CLOB':
            case 'XMLTYPE':
                $field = [
                    'type' => TableSchema::TYPE_TEXT,
                    'length' => $row['char_length'],
                ];
                break;
            case 'RAW':
            case 'LONG RAW':
            case 'BLOB':
                $field = [
                    'type' => TableSchema::TYPE_BINARY,
                    'length' => $row['char_length'],
                ];
                break;
            default:
        }
        $field += [
            'null' => $row['null'] === 'Y',
            'default' => $row['default'],
            'comment' => $row['comment'],
        ];
        $schema->addColumn($this->_transformValueCase($row['name']), $field);
    }

    /**
     * Generate the SQL to describe a parameter.
     *
     * @param string $objectName The table name to get information on.
     * @param array $config The connection configuration.
     * @return array An array of (sql, params) to execute.
     */
    public function describeParametersSql($objectName, $config)
    {
        $items = explode('.', $objectName);
        $itemsCount = count($items);
        if ($itemsCount === 3) {
            [$schema, $package, $object] = explode('.', $objectName);
        } elseif ($itemsCount === 2) {
            [$package, $object] = explode('.', $objectName);
        } else {
            $schema = $package = null;
            $object = $objectName;
        }
        $ownerCondition = '';
        $params = [
            ':objectParam' => $object,
        ];
        if (empty($schema) && empty($package)) {
            $argumentsTable = 'user_arguments';
            $useOwner = false;
        } else {
            $argumentsTable = 'all_arguments';
            $useOwner = true;
            if (!empty($package) && !empty($schema)) {
                $ownerCondition = 'AND args.OWNER = :ownerParam AND args.PACKAGE_NAME = :packageParam ';
                $params = [
                    ':objectParam' => $object,
                    ':packageParam' => $package,
                    ':ownerParam' => $schema,
                ];
            } elseif (!empty($package)) {
                $ownerCondition = 'AND (args.OWNER = :packageParam OR args.PACKAGE_NAME = :packageParam) ';
                $params = [
                    ':objectParam' => $object,
                    ':packageParam' => $package,
                ];
            }
        }
        $sql = "SELECT
                    args.object_name as \"object\",
                    args.package_name AS package,
                    args.argument_name AS name,
                    args.data_type AS type,
                    args.pls_type AS pls_type,
                    args.in_out AS direction,
                    args.data_length,
                    args.data_precision,
                    args.data_scale,
                    args.position AS ordinal_position
                FROM $argumentsTable args
                WHERE UPPER(args.object_name) = :objectParam
                " . ($useOwner ? $ownerCondition : '') . "
                ORDER BY args.position";

        return [
            $sql,
            $params,
        ];
    }

    /**
     * Convert parameter description results into abstract schema fields.
     *
     * @param \CakeDC\OracleDriver\Database\Schema\MethodSchema $method The method object to append parameters to.
     * @param array $row The row data from `describeParametersSql`.
     * @return void
     */
    public function convertParametersDescription(MethodSchema $method, $row)
    {
        $row = array_change_key_case($row);
        switch ($row['type']) {
            case 'DATE':
                $field = [
                    'type' => TableSchema::TYPE_DATETIME,
                    'length' => null,
                ];
                break;
            case 'TIMESTAMP':
            case 'TIMESTAMP(6)':
            case 'TIMESTAMP(9)':
                $field = [
                    'type' => TableSchema::TYPE_TIMESTAMP,
                    'length' => null,
                ];
                break;
            case 'NUMBER':
            case 'INTEGER':
            case 'PLS_INTEGER':
            case 'BINARY_INTEGER':
                $field = $this->_numberFieldDefinition($row);
                break;
            case 'FLOAT':
            case 'BINARY_FLOAT':
            case 'BINARY_DOUBLE':
                $field = [
                    'type' => TableSchema::TYPE_FLOAT,
                    'length' => $row['data_precision'],
                ];
                break;
            case 'NCHAR':
            case 'NVARCHAR2':
            case 'CHAR':
            case 'VARCHAR2':
            case 'LONG':
            case 'ROWID':
            case 'UROWID':
                $length = $row['data_length'];
                if ($length == 36) {
                    $field = [
                        'type' => TableSchema::TYPE_UUID,
                        'length' => null,
                    ];
                } else {
                    $field = [
                        'type' => TableSchema::TYPE_STRING,
                        'length' => $length,
                    ];
                }
                break;
            case 'NCLOB':
            case 'CLOB':
                $field = [
                    'type' => TableSchema::TYPE_TEXT,
                    'length' => $row['data_length'],
                ];
                break;
            case 'RAW':
            case 'LONG RAW':
            case 'BLOB':
                $field = [
                    'type' => TableSchema::TYPE_BINARY,
                    'length' => $row['data_length'],
                ];
                break;
            case 'REF CURSOR':
                $field = [
                    'type' => 'cursor',
                ];
                break;
            default:
        }
        $out = strpos($row['direction'], 'OUT') !== false;
        $name = $row['name'];
        $function = $out && $name === null;
        $field += [
            'in' => strpos($row['direction'], 'IN') !== false,
            'out' => $out,
            'function' => $function,
        ];
        if ($function) {
            $name = ':result';
        }
        $method->addParameter($name, $field);
    }

    /**
     * {@inheritDoc}
     */
    public function describeIndexSql(string $tableName, array $config): array
    {
        $this->_constraints[$tableName] = [];
        [$schema, $table] = $this->tableSplit($tableName, $config);
        if (empty($schema)) {
            $constraintsTable = 'user_constraints';
            $indexesTable = 'user_indexes';
            $indexColumnsTable = 'user_ind_columns';
            $useOwner = false;
        } else {
            $constraintsTable = 'all_constraints';
            $indexesTable = 'all_indexes';
            $indexColumnsTable = 'all_ind_columns';
            $useOwner = true;
        }

        $sql = "SELECT
            ic.index_name AS name,
            (
                SELECT i.index_type
                FROM   $indexesTable i
                WHERE  i.index_name = ic.index_name" . ($useOwner ? ' AND ic.table_owner = i.table_owner' : '') . "
            ) AS type,
            decode(
                (
                     SELECT i.uniqueness
                     FROM   $indexesTable i
                     WHERE  i.index_name = ic.index_name" . ($useOwner ? ' AND ic.table_owner = i.table_owner' : '') . "
                ),
                'NONUNIQUE', 0,
                'UNIQUE', 1
            ) AS is_unique,
            ic.column_name AS column_name,
            ic.column_position AS column_pos,
            (
                SELECT c.constraint_type
                FROM   $constraintsTable c
                WHERE  c.constraint_name = ic.index_name" . ($useOwner ? ' AND c.owner = ic.index_owner' : '') . "
             ) AS is_primary
             FROM $indexColumnsTable ic
             WHERE upper(ic.table_name) = :tableParam" . ($useOwner ? ' AND ic.table_owner = :ownerParam' : '') . "
            ORDER BY ic.column_position ASC";

        $params = [
            ':tableParam' => $table,
        ];
        if ($useOwner) {
            $params[':ownerParam'] = $schema;
        }

        return [$sql, $params];
    }

    /**
     * {@inheritDoc}
     */
    public function convertIndexDescription(TableSchema $schema, array $row): void
    {
        $tableIndex = array_change_key_case($row);
        $type = null;
        $columns = $length = [];

        if (preg_match('~FUNCTION-BASED~i', ($tableIndex['type'] ?? ''))) {
            return;
        }

        $keyName = $this->_transformValueCase($tableIndex['name']);
        $name = $this->_transformValueCase($tableIndex['column_name']);
        if ( !empty($tableIndex['is_primary']) && strtolower($tableIndex['is_primary']) === 'p') {
            $keyName = $type = TableSchema::CONSTRAINT_PRIMARY;
        } elseif ($tableIndex['is_unique'] !== []) {
            $type = TableSchema::CONSTRAINT_UNIQUE;
        } else {
            $type = TableSchema::INDEX_INDEX;
        }

        $columns[] = $this->_transformValueCase($name);

        $isIndex = $type === TableSchema::INDEX_INDEX;
        $existing = $isIndex ? $schema->getIndex($keyName) : $schema->getConstraint($keyName);

        if (!empty($existing)) {
            $columns = array_merge($existing['columns'], $columns);
        }
        if ($isIndex) {
            $schema->addIndex($keyName, [
                'type' => $type,
                'columns' => $columns,
            ]);
        } else {
            $schema->addConstraint($keyName, [
                'type' => $type,
                'columns' => $columns,
            ]);
        }
    }

    /**
     * Convert a column list into a clean array.
     *
     * @param string $columns comma separated column list.
     * @return array
     */
    protected function _convertColumnList($columns)
    {
        $columns = explode(', ', $columns);
        foreach ($columns as &$column) {
            $column = trim($column, '"');
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    public function describeForeignKeySql(string $tableName, array $config): array
    {
        [$schema, $table] = $this->tableSplit($tableName, $config);

        if (empty($schema)) {
            $sql = "SELECT
                        cc.column_name,
                        cc.constraint_name,
                        r.owner as referenced_owner,
                        r.table_name as referenced_table_name,
                        r.column_name as referenced_column_name,
                        c.delete_rule
                    FROM user_cons_columns cc
                    JOIN user_constraints c ON c.constraint_name = cc.constraint_name
                    JOIN user_cons_columns r ON r.constraint_name = c.r_constraint_name
                    WHERE c.constraint_type = 'R'
                    AND upper(cc.table_name) = :tableParam
                    ";

            return [
                $sql,
                [':tableParam' => $table],
            ];
        }
        $sql = "
            SELECT
                cc.column_name,
                cc.constraint_name,
                r.owner as referenced_owner,
                r.table_name as referenced_table_name,
                r.column_name as referenced_column_name,
                c.delete_rule
            FROM all_cons_columns cc
            JOIN all_constraints c ON (c.constraint_name = cc.constraint_name AND c.owner = cc.owner)
            JOIN all_cons_columns r ON (r.constraint_name = c.r_constraint_name AND r.owner = c.r_owner)
            WHERE c.constraint_type = 'R'
            AND cc.owner = :ownerParam
            AND upper(cc.table_name) = :tableParam";

        return [
            $sql,
            [
                ':tableParam' => $table,
                ':ownerParam' => $schema,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function convertForeignKeyDescription(TableSchema $schema, array $row): void
    {
        $row = array_change_key_case($row);
        // Fix: fk data case must be transformed
        $column_name = $this->_transformValueCase($row['column_name']);
        $constraint_name = $this->_transformValueCase($row['constraint_name']);
        $referenced_table_name = $this->_transformValueCase($row['referenced_table_name']);
        $referenced_column_name = $this->_transformValueCase($row['referenced_column_name']);
        $data = [
            'type' => TableSchema::CONSTRAINT_FOREIGN,
            'columns' => $column_name,
            'references' => [
                $row['referenced_owner'] . '.' . $referenced_table_name,
                $referenced_column_name,
            ],
            'update' => TableSchema::ACTION_SET_NULL,
            'delete' => $this->_convertOnClause($row['delete_rule']),
        ];
        $schema->addConstraint($constraint_name, $data);
    }

    /**
     * {@inheritDoc}
     */
    protected function _convertOnClause(string $clause): string
    {
        if ($clause === 'RESTRICT') {
            return TableSchema::ACTION_RESTRICT;
        }
        if ($clause === 'NO ACTION') {
            return TableSchema::ACTION_NO_ACTION;
        }
        if ($clause === 'CASCADE') {
            return TableSchema::ACTION_CASCADE;
        }

        return TableSchema::ACTION_SET_NULL;
    }

    /**
     * {@inheritDoc}
     */
    public function columnSql(TableSchema $schema, string $name): string
    {
        $data = $schema->getColumn($name);
        $out = $this->_driver->quoteIfAutoQuote($name);
        $typeMap = [
            TableSchema::TYPE_INTEGER => ' NUMBER',
            TableSchema::TYPE_SMALLINTEGER => ' NUMBER',
            TableSchema::TYPE_TINYINTEGER => ' NUMBER',
            TableSchema::TYPE_BIGINTEGER => ' NUMBER',
            TableSchema::TYPE_BOOLEAN => ' NUMBER(1)',
            TableSchema::TYPE_BINARY => ' BLOB',
            TableSchema::TYPE_FLOAT => ' FLOAT',
            TableSchema::TYPE_DECIMAL => ' NUMBER',
            TableSchema::TYPE_TEXT => ' CLOB',
            TableSchema::TYPE_DATE => ' TIMESTAMP',
            TableSchema::TYPE_TIME => ' TIMESTAMP',
            TableSchema::TYPE_DATETIME => ' TIMESTAMP',
            TableSchema::TYPE_TIMESTAMP => ' TIMESTAMP',
            TableSchema::TYPE_UUID => ' VARCHAR2(36)',
        ];

        if (!isset($typeMap[$data['type']]) && $data['type'] != TableSchema::TYPE_STRING) {
            throw new UnallowedDataTypeException(['type' => $data['type']]);
        }

        if (isset($typeMap[$data['type']])) {
            $out .= $typeMap[$data['type']];
        }

        if ($data['type'] === TableSchema::TYPE_STRING) {
            $isFixed = !empty($data['fixed']);
            $type = ' VARCHAR2';
            if ($isFixed) {
                $type = ' CHAR';
            }
            $out .= $type;
            if (!isset($data['length'])) {
                $data['length'] = 255;
            }
            $out .= '(' . (int)$data['length'] . ')';
        }

        if ($this->__isInteger($data['type']) && !isset($data['length'])) {
            $data['length'] = $this->integerTypes[$data['type']];
        }

        if ($this->__isInteger($data['type']) && isset($data['length'])) {
            $out .= '(' . (int)$data['length'] . ')';
        }

        if (($data['type'] === TableSchema::TYPE_FLOAT || $data['type'] === TableSchema::TYPE_DECIMAL) && (isset($data['length']) || isset($data['precision']))) {
            $out .= '(' . (int)$data['length'] . ',' . (int)$data['precision'] . ')';
        }

        if ($this->_driver->useAutoincrement()) {
            if (
                $this->__isInteger($data['type']) && (array)$schema->getPrimaryKey() ===
                [$name]
            ) {
                $out .= ' GENERATED BY DEFAULT AS IDENTITY (START WITH 1)';
                //$out .= ' GENERATED ALWAYS AS IDENTITY START WITH 1 INCREMENT BY 1';
            }
        }

        if (isset($data['null']) && $data['null'] === true) {
            $out .= ' DEFAULT NULL';
            unset($data['default']);
        }
        if (isset($data['default']) && $data['type'] !== TableSchema::TYPE_TIMESTAMP) {
            $defaultValue = $data['default'];
            if ($data['type'] === TableSchema::TYPE_BOOLEAN) {
                $defaultValue = (int)$defaultValue;
            }
            $out .= ' DEFAULT ' . $this->_driver->schemaValue($defaultValue);
        }

        if (isset($data['null']) && $data['null'] === false) {
            $out .= ' NOT NULL';
        }

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function addConstraintSql(TableSchema $schema): array
    {
        $sqlPattern = 'ALTER TABLE %s ADD %s;';
        $sql = [];

        foreach ($schema->constraints() as $name) {
            $constraint = $schema->getConstraint($name);
            if ($constraint['type'] === TableSchema::CONSTRAINT_FOREIGN) {
                $tableName = $this->_driver->quoteIfAutoQuote($schema->name());
                $sql[] = sprintf($sqlPattern, $tableName, $this->constraintSql($schema, $name));
            }
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function dropConstraintSql(TableSchema $schema): array
    {
        $sqlPattern = 'ALTER TABLE %s DROP CONSTRAINT %s;';
        $sql = [];

        foreach ($schema->constraints() as $name) {
            $constraint = $schema->getConstraint($name);
            if ($constraint['type'] === TableSchema::CONSTRAINT_FOREIGN) {
                $tableName = $this->_driver->quoteIfAutoQuote($schema->name());
                $constraintName = $this->_driver->quoteIfAutoQuote($name);
                $sql[] = sprintf($sqlPattern, $tableName, $constraintName);
            }
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function indexSql(TableSchema $schema, string $name): string
    {
        $data = $schema->getIndex($name);
        $columns = array_map([
            $this->_driver,
            'quoteIfAutoQuote',
        ], $data['columns']);

        return sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $this->_driver->quoteIfAutoQuote($name),
            $this->_driver->quoteIfAutoQuote($schema->name()),
            implode(', ', $columns)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function constraintSql(TableSchema $schema, string $name): string
    {
        $data = $schema->getConstraint($name);
        $out = 'CONSTRAINT ' . $this->_driver->quoteIfAutoQuote($name);
        if ($data['type'] === TableSchema::CONSTRAINT_PRIMARY) {
            $out = 'PRIMARY KEY';
        }
        if ($data['type'] === TableSchema::CONSTRAINT_UNIQUE) {
            $out .= ' UNIQUE';
        }

        return $this->_keySql($out, $data);
    }

    /**
     * Helper method for generating key SQL snippets.
     *
     * @param string $prefix The key prefix
     * @param array $data Key data.
     * @return string
     */
    protected function _keySql($prefix, $data)
    {
        $columns = array_map([
            $this->_driver,
            'quoteIfAutoQuote',
        ], $data['columns']);
        if ($data['type'] === TableSchema::CONSTRAINT_FOREIGN) {
            return $prefix . sprintf(
                ' FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s DEFERRABLE INITIALLY IMMEDIATE',
                implode(', ', $columns),
                $this->_driver->quoteIfAutoQuote($data['references'][0]),
                $this->_convertConstraintColumns($data['references'][1]),
                $this->_foreignOnClause($data['update']),
                $this->_foreignOnClause($data['delete'])
            );
        }

        return $prefix . ' (' . implode(', ', $columns) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function createTableSql(
        TableSchema $schema,
        array $columns,
        array $constraints,
        array $indexes
    ): array {
        $content = array_merge($columns, $constraints);
        $content = implode(",\n", array_filter($content));
        $tableName = $this->_driver->quoteIfAutoQuote($schema->name());
        $temporary = $schema->isTemporary() ? ' TEMPORARY ' : ' ';
        $out = [];
        $out[] = sprintf("CREATE%sTABLE %s (\n%s\n)", $temporary, $tableName, $content);
        foreach ($indexes as $index) {
            $out[] = $index;
        }
        foreach ($schema->columns() as $column) {
            $columnData = $schema->getColumn($column);
            if (isset($columnData['comment'])) {
                $out[] = sprintf(
                    'COMMENT ON COLUMN %s.%s IS %s',
                    $tableName,
                    $this->_driver->quoteIfAutoQuote($column),
                    $this->_driver->schemaValue($columnData['comment'])
                );
            }
        }

        $pk = $this->_getPrimaryKey($schema);
        if ($pk) {
            $fieldName = $pk['columns'][0];
            $out = Hash::merge($out, $this->getCreateAutoincrementSql($fieldName, $schema->name()));
        }

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function truncateTableSql(TableSchema $schema): array
    {
        $name = $this->_driver->quoteIfAutoQuote($schema->name());
        $sequenceName = $this->_getSequenceName($schema->name());
        $result = [
            sprintf('TRUNCATE TABLE %s', $name),
            $this->dropSequenceIfExists($sequenceName),
            $this->createSequenceIfNotExists($sequenceName),
        ];

        $keys = $schema->getPrimaryKey();
        if (count($keys) == 1 && $this->_driver->useAutoincrement()) {
            $primaryKeyField = $schema->getColumn($keys[0]);
            if ($this->__isInteger($primaryKeyField['type'])) {
                $primaryKey = $this->_driver->quoteIfAutoQuote($keys[0]);
                $result[] = sprintf(
                    'ALTER TABLE %s MODIFY(%s GENERATED AS IDENTITY (START WITH 1))',
                    $name,
                    $primaryKey
                );
            }
        }

        return $result;
    }

    /**
     * Generate the SQL to drop a table.
     *
     * @param \Cake\Database\Schema\TableSchema $table TableSchema instance
     * @return array SQL statements to drop a table.
     */
    public function dropTableSql(TableSchema $table): array
    {
        $sql = sprintf('DROP TABLE %s CASCADE CONSTRAINTS', $this->_driver->quoteIfAutoQuote($table->name()));

        return [$sql];
    }

    /**
     * Builds array with schema and table names.
     *
     * @param string $name Table name optionally with schema name.
     * @param array $config The connection configuration.
     * @return string
     */
    public function tableSplit($name, $config)
    {
        $name = strtoupper($name);
        $schema = null;
        $table = $name;
        if (strpos($name, '.') !== false) {
            [$schema, $table] = explode('.', $table);
        } elseif (!empty($config['schema'])) {
            $schema = strtoupper($config['schema']);
        }

        return [
            $schema,
            $table,
        ];
    }

    /**
     * Returns table primary key.
     *
     * @param \Cake\Database\Schema\TableSchema $table Table schema object.
     * @return array|null
     */
    protected function _getPrimaryKey(TableSchema $table)
    {
        $constraints = $table->constraints();
        foreach ($constraints as $name) {
            $constraint = $table->getConstraint($name);
            if ($this->_isSingleKey($table, [$constraint])) {
                return $constraint;
            }
        }

        return null;
    }

    /**
     * Checks if table primary key has single column.
     *
     * @param \Cake\Database\Schema\TableSchema $table Table schema object.
     * @param array $constraints Constraints list.
     * @return bool
     */
    protected function _isSingleKey(TableSchema $table, $constraints)
    {
        if (count($constraints) !== 1) {
            return false;
        }
        $constraint = $constraints[0];
        $columns = $constraint['columns'];
        if ((is_countable($columns) ? count($columns) : 0) !== 1) {
            return false;
        }
        $column = $table->getColumn($columns[0]);

        return $column['type'] === 'integer' && $constraint['type'] === TableSchema::CONSTRAINT_PRIMARY;
    }

    /**
     * Create sequence in database.
     *
     * @param string $sequenceName Sequence name.
     * @return string
     */
    public function getCreateSequenceSql($sequenceName)
    {
        return 'CREATE SEQUENCE ' . $sequenceName . ' START WITH 1' . ' MINVALUE 1' . ' INCREMENT BY 1' . ' CACHE 20';
    }

    /**
     * Remove sequence from database.
     *
     * @param string $sequenceName Sequence name.
     * @return string
     */
    public function getDropSequenceSql($sequenceName)
    {
        return 'DROP SEQUENCE ' . $sequenceName;
    }

    /**
     *
     *
     * @param string $name Sequence name.
     * @param string $createCommand Operation to execute.
     * @return string
     */
    public function createSequenceIfNotExists($name, $createCommand = null)
    {
        $name = strtoupper($name);
        if (empty($createCommand)) {
            $createCommand = $this->getCreateSequenceSql($name);
        }

        return <<<SQL
        declare
            ex NUMBER;
        begin
            SELECT count(*) INTO ex FROM user_sequences WHERE sequence_name = '$name';
            if (ex = 0) then
                execute immediate '$createCommand';
            end if;
        end;
SQL;
    }

    /**
     *
     *
     * @param string $name Sequence name.
     * @param null $dropCommand Operation to execute.
     * @return string
     */
    public function dropSequenceIfExists($name, $dropCommand = null)
    {
        $name = strtoupper($name);
        if (empty($dropCommand)) {
            $dropCommand = $this->getDropSequenceSql($name);
        }

        return <<<SQL
        declare
            ex NUMBER;
        begin
            SELECT count(*) INTO ex FROM user_sequences WHERE sequence_name = '$name';
            if (ex = 1) then
                execute immediate '$dropCommand';
            end if;
        end;
SQL;
    }

    /**
     * Generates trigger for autoincrement field. Used when apply schema for example when create table in text schema.
     *
     * @param string $name Primary key name.
     * @param string $tableName Table name.
     * @param int $start Start index for autoincrement field.
     * @return array
     */
    public function getCreateAutoincrementSql($name, $tableName, $start = 1)
    {
        if ($this->_driver->useAutoincrement()) {
            return [];
        }
        $quotedTableName = $this->_driver->quoteIfAutoQuote($tableName);

        $quotedName = $this->_driver->quoteIfAutoQuote($name);

        $sql = [];

        $autoincrementIdentifierName = 't_' . $tableName;
        $sequenceName = $this->_getSequenceName($tableName);

        $sql[] = $this->createSequenceIfNotExists($sequenceName, $this->getCreateSequenceSQL($sequenceName));

        $sql[] = 'CREATE TRIGGER ' . $autoincrementIdentifierName . '
   BEFORE INSERT
   ON ' . $quotedTableName . '
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   SELECT ' . $sequenceName . '.NEXTVAL INTO :NEW.' . $quotedName . ' FROM DUAL;
   IF (:NEW.' . $quotedName . ' IS NULL OR :NEW.' . $quotedName . ' = 0) THEN
      SELECT ' . $sequenceName . '.NEXTVAL INTO :NEW.' . $quotedName . ' FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = \'' . strtoupper($sequenceName) . '\';
      SELECT :NEW.' . $quotedName . ' INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT ' . $sequenceName . '.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;';

        return $sql;
    }

    /**
     * Generates sequence name based on convention that sequence names based on table name with prefix "SEQ_"
     *
     * @param string $name Original table name.
     * @return mixed
     */
    protected function _getSequenceName($name)
    {
        $name = 'seq_' . $name;

        return strtoupper($name);
    }

    /**
     * Transform string case on php side.
     *
     * @param string $value Binding value.
     * @return mixed
     */
    protected function _transformValueCase($value)
    {
        $case = $this->_driver->config('case');
        if ($case == 'lower') {
            return strtolower($value);
        }

        return $value;
    }

    /**
     * Transform field case in sql response.
     *
     * @param string $field Field name.
     * @return string
     */
    protected function _transformFieldCase($field)
    {
        $case = $this->_driver->config('case');
        if ($case == 'lower') {
            return "lower($field)";
        }

        return $field;
    }

    /**
     * Check for integer type.
     *
     * @param string $type Field type.
     *
     * @return bool
     */
    private function __isInteger(string $type): bool
    {
        $integerTypes = [
            TableSchema::TYPE_INTEGER => true,
            TableSchema::TYPE_SMALLINTEGER => true,
            TableSchema::TYPE_TINYINTEGER => true,
            TableSchema::TYPE_BIGINTEGER => true,
        ];

        return array_key_exists($type, $integerTypes);
    }

    /**
     * Build number field definition
     *
     * @param array $row
     * @return array
     */
    protected function _numberFieldDefinition(array $row): array
    {
        if ($row['data_precision'] == null) {
            $field = [
                'type' => 'decimal',
                'length' => $row['char_length'] ?? null,
            ];
        } elseif ($row['data_precision'] == 1) {
            $field = [
                'type' => TableSchema::TYPE_BOOLEAN,
                'length' => null,
            ];
        } elseif ($row['data_scale'] > 0) {
            $field = [
                'type' => TableSchema::TYPE_DECIMAL,
                'length' => $row['data_precision'],
                'precision' => $row['data_scale'],
            ];
        } else {
            $field = [
                'type' => TableSchema::TYPE_INTEGER,
                'length' => $row['data_precision'],
            ];
        }

        return $field;
    }
}
