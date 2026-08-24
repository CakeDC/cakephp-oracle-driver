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
namespace CakeDC\OracleDriver\Database\Dialect;

use Cake\Database\Expression\FunctionExpression;
use Cake\Database\ExpressionInterface;
use Cake\Database\Query;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Schema\SchemaDialect;
use CakeDC\OracleDriver\Database\Expression\SimpleExpression;
use CakeDC\OracleDriver\Database\Schema\OracleSchema;

/**
 * Contains functions that encapsulates the SQL dialect used by Oracle,
 * including query translators and schema introspection.
 */
trait OracleDialectTrait
{
    /**
     * Distinct clause needs no transformation.
     *
     * @param \Cake\Database\Query\SelectQuery $query The query to be transformed
     * @return \Cake\Database\Query\SelectQuery
     */
    protected function _transformDistinct(SelectQuery $query): SelectQuery
    {
        return $query;
    }

    /**
     * Modify the limit/offset to oracle.
     *
     * @param \Cake\Database\Query\SelectQuery $query The query to translate
     * @return \Cake\Database\Query\SelectQuery The modified query
     */
    protected function _selectQueryTranslator(SelectQuery $query): SelectQuery
    {
        $limit = $query->clause('limit');
        $offset = $query->clause('offset');

        if ($offset !== null || $limit !== null) {
            if ($this->_serverVersion !== null && $this->_serverVersion >= 12) {
                return $this->_pagingSubquery12($query, $limit, $offset);
            }

            return $this->_pagingSubquery($query, $limit, $offset);
        }

        return $this->_transformDistinct($query);
    }

    /**
     * Generate a paging subquery for older versions of Oracle Server.
     *
     * @param \Cake\Database\Query\SelectQuery $original The query to wrap in a subquery.
     * @param int|null $limit The number of rows to fetch.
     * @param int|null $offset The number of rows to offset.
     * @return \Cake\Database\Query\SelectQuery Modified query object.
     */
    protected function _pagingSubquery(SelectQuery $original, ?int $limit, ?int $offset): SelectQuery
    {
        $field = 'cake_paging_out."_cake_page_rownum_"';

        $query = clone $original;
        $query->limit(null)
            ->offset(null);

        $connection = $query->getConnection();
        $outer = $connection->selectQuery();
        $outer
            ->select([
                'cake_paging.*',
                '_cake_page_rownum_' => new SimpleExpression('ROWNUM'),
            ])
            ->from(['cake_paging' => $query]);

        $outer2 = $connection->selectQuery();
        $outer2->select('*')
            ->from(['cake_paging_out' => $outer]);

        if ($offset) {
            $outer2->where(["$field > " . (int)$offset]);
        }

        if ($limit) {
            $value = (int)$offset + (int)$limit;
            $outer2->where(["$field <= $value"]);
        }

        $original->decorateResults(function (array $row): array {
            if (is_object($row) && property_exists($row, '_cake_page_rownum_')) {
                unset($row->_cake_page_rownum_);
            } elseif (isset($row['_cake_page_rownum_'])) {
                unset($row['_cake_page_rownum_']);
            }

            return $row;
        });

        return $outer2;
    }

    /**
     * Generate a paging subquery for Oracle 12+.
     *
     * @param \Cake\Database\Query\SelectQuery $original The query to wrap in a subquery.
     * @param int|null $limit The number of rows to fetch.
     * @param int|null $offset The number of rows to offset.
     * @return \Cake\Database\Query\SelectQuery Modified query object.
     */
    protected function _pagingSubquery12(SelectQuery $original, ?int $limit, ?int $offset): SelectQuery
    {
        return $original;
    }

    /**
     * Returns a dictionary of expressions to be transformed when compiling a Query
     * to SQL. Array keys are method names to be called in this class.
     *
     * @return array<class-string, string>
     */
    protected function _expressionTranslators(): array
    {
        return [
            FunctionExpression::class => '_transformFunctionExpression',
        ];
    }

    /**
     * Receives a FunctionExpression and changes it so that it conforms to this SQL dialect.
     *
     * @param \Cake\Database\Expression\FunctionExpression $expression The function expression to convert to oracle SQL.
     * @param \Cake\Database\Query $query The query being compiled.
     * @return void
     */
    protected function _transformFunctionExpression(FunctionExpression $expression, Query $query): void
    {
        switch ($expression->getName()) {
            case 'RAND':
                $expression->setName('DBMS_RANDOM.VALUE');
                break;
            case 'CONCAT':
                $expression->setName('')->setConjunction(' ||');
                break;
            case 'DATEDIFF':
                $expression
                    ->setName('')
                    ->setConjunction('-')
                    ->iterateParts(function ($p): FunctionExpression {
                        if (is_string($p)) {
                            $p = ['value' => [$p => 'literal'], 'type' => null];
                        } else {
                            $p['value'] = [$p['value']];
                        }

                        return new FunctionExpression('TO_DATE', $p['value'], [$p['type']]);
                    });
                break;
            case 'CURRENT_DATE':
                $time = new FunctionExpression('LOCALTIMESTAMP', [' 0 ' => 'literal']);
                $expression->setName('TO_CHAR')->add([$time, 'YYYY-MM-DD']);
                break;
            case 'CURRENT_TIME':
                $time = new FunctionExpression('LOCALTIMESTAMP', [' 0 ' => 'literal']);
                $expression->setName('TO_CHAR')->add([$time, 'YYYY-MM-DD HH24:MI:SS']);
                break;
            case 'NOW':
                $expression->setName('LOCALTIMESTAMP')->add([' 0 ' => 'literal']);
                break;
            case 'DATE_ADD':
                $expression
                    ->setName('TO_CHAR')
                    ->setConjunction(' + INTERVAL')
                    ->iterateParts(function ($p, $key) {
                        if ($key === 1) {
                            $keys = explode(' ', $p);
                            $unit = array_pop($keys);
                            $value = implode(' ', $keys);
                            $value = str_replace("'", '', $value);
                            $p = sprintf("'%s' %s", $value, $unit);
                        }

                        return $p;
                    });
                break;
            case 'DAYOFWEEK':
                $expression
                    ->setName('TO_CHAR')
                    ->add(['d']);
                break;
            case 'JSON_VALUE':
                break;
        }
    }

    /**
     * Get the schema dialect.
     *
     * @return \CakeDC\OracleDriver\Database\Schema\OracleSchema
     */
    public function schemaDialect(): SchemaDialect
    {
        if (!isset($this->_schemaDialect)) {
            $this->_schemaDialect = new OracleSchema($this);
        }

        return $this->_schemaDialect;
    }

    /**
     * {@inheritDoc}
     *
     * @see http://www.dba-oracle.com/t_enabling_disabling_constraints.htm
     */
    public function disableForeignKeySQL(): string
    {
        return $this->_processAllForeignKeys('disable');
    }

    /**
     * {@inheritDoc}
     *
     * @see http://www.dba-oracle.com/t_enabling_disabling_constraints.htm
     */
    public function enableForeignKeySQL(): string
    {
        return $this->_processAllForeignKeys('enable');
    }

    /**
     * Get the SQL for enabling or disabling foreign keys.
     *
     * @param string $type "enable" or "disable"
     * @return string
     */
    protected function _processAllForeignKeys(string $type): string
    {
        $startQuote = $this->_startQuote;
        $endQuote = $this->_endQuote;
        if (!empty($this->_config['schema'])) {
            $schemaName = strtoupper($this->_config['schema']);
            $fromWhere = "from all_constraints
                where owner = '{$schemaName}' and constraint_type = 'R'";
        } else {
            $fromWhere = "from user_constraints
                where constraint_type = 'R'";
        }

        return "declare
            cursor c is select owner, table_name, constraint_name
                {$fromWhere};
            begin
                for r in c loop
                    execute immediate 'alter table " .
                    "{$startQuote}' || r.owner || '{$endQuote}." .
                    "{$startQuote}' || r.table_name || '{$endQuote} " .
                    "{$type} constraint " .
                    "{$startQuote}' || r.constraint_name || '{$endQuote}';
                end loop;
            end;";
    }

    /**
     * Transforms an insert query that is meant to insert multiple rows at a time,
     * otherwise it leaves the query untouched.
     *
     * @param \Cake\Database\Query\InsertQuery $query The query to translate
     * @return \Cake\Database\Query\InsertQuery
     */
    protected function _insertQueryTranslator(InsertQuery $query): InsertQuery
    {
        $v = $query->clause('values');
        if ((is_countable($v->getValues()) ? count($v->getValues()) : 0) === 1 || $v->getQuery()) {
            return $query;
        }

        $connection = $query->getConnection();
        $newQuery = $connection->selectQuery();
        $cols = $v->getColumns();
        $placeholder = 0;
        $replaceQuery = false;

        foreach ($v->getValues() as $k => $val) {
            $fillLength = (is_countable($cols) ? count($cols) : 0) - (is_countable($val) ? count($val) : 0);
            if ($fillLength > 0) {
                $val = array_merge($val, array_fill(0, $fillLength, null));
            }

            foreach ($val as $col => $attr) {
                if (!($attr instanceof ExpressionInterface)) {
                    $val[$col] = sprintf(':c%d', $placeholder);
                    $placeholder++;
                }
            }

            $select = array_combine($cols, $val);
            if ($k === 0) {
                $replaceQuery = true;
                $newQuery->select($select)->from('DUAL');
                continue;
            }

            $q = $connection->selectQuery();
            $newQuery->unionAll($q->select($select)->from('DUAL'));
        }

        if ($replaceQuery) {
            $v->setQuery($newQuery);
        }

        return $query;
    }
}
