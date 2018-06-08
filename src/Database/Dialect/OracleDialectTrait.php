<?php
/**
 * Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\OracleDriver\Database\Dialect;

use CakeDC\OracleDriver\Database\Expression\SimpleExpression;
use CakeDC\OracleDriver\Database\OracleCompiler;
use CakeDC\OracleDriver\Database\Schema\OracleSchema;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\Query;
use Cake\Database\SqlDialectTrait;

/**
 * Contains functions that encapsulates the SQL dialect used by Oracle,
 * including query translators and schema introspection.
 */
trait OracleDialectTrait
{

    use SqlDialectTrait;

    /**
     *  String used to start a database identifier quoting to make it safe
     *
     * @var string
     */
    protected $_startQuote = '"';

    /**
     * String used to end a database identifier quoting to make it safe
     *
     * @var string
     */
    protected $_endQuote = '"';

    /**
     * The schema dialect class for this driver
     *
     * @var \CakeDC\OracleDriver\Database\Schema\OracleSchema
     */
    protected $_schemaDialect;

    /**
     * Distinct clause needs no transformation
     *
     * @param \Cake\Database\Query $query The query to be transformed
     * @return \Cake\Database\Query
     */
    protected function _transformDistinct($query)
    {
        return $query;
    }

    /**
     * Modify the limit/offset to oracle
     *
     * @param \Cake\Database\Query $query The query to translate
     * @return \Cake\Database\Query The modified query
     */
    protected function _selectQueryTranslator($query)
    {
        $limit = $query->clause('limit');
        $offset = $query->clause('offset');

        if ($offset !== null || $limit !== null) {
            return $this->_pagingSubquery($query, $limit, $offset);
        }

        return $this->_transformDistinct($query);
    }

    /**
     * Generate a paging subquery for older versions of Oracle Server.
     *
     * Prior to Oracle 12 there was no equivalent to LIMIT OFFSET,
     * so a subquery must be used.
     *
     * @param \Cake\Database\Query $original The query to wrap in a subquery.
     * @param int $limit The number of rows to fetch.
     * @param int $offset The number of rows to offset.
     * @return \Cake\Database\Query Modified query object.
     */
    protected function _pagingSubquery($original, $limit, $offset)
    {
        $field = 'cake_paging_out."_cake_page_rownum_"';

        $query = clone $original;
        $query->limit(null)
            ->offset(null);

        $outer = new Query($query->getConnection());
        $outer
            ->select([
                'cake_paging.*',
                '_cake_page_rownum_' => new SimpleExpression('ROWNUM')
            ])
            ->from(['cake_paging' => $query]);

        $outer2 = new Query($query->getConnection());
        $outer2->select('*')
            ->from(['cake_paging_out' => $outer]);

        if ($offset) {
            $outer2->where(["$field > " . (int)$offset]);
        }
        if ($limit) {
            $value = (int)$offset + (int)$limit;
            $outer2->where(["$field <= $value"]);
        }

        $original->decorateResults(function ($row) {
            if (isset($row['_cake_page_rownum_'])) {
                unset($row['_cake_page_rownum_']);
            }
            return $row;
        });
        return $outer2;
    }

    /**
     * Returns a dictionary of expressions to be transformed when compiling a Query
     * to SQL. Array keys are method names to be called in this class
     *
     * @return array
     */
    protected function _expressionTranslators()
    {
        $namespace = 'Cake\Database\Expression';
        return [
            $namespace . '\FunctionExpression' => '_transformFunctionExpression'
        ];
    }

    /**
     * Receives a FunctionExpression and changes it so that it conforms to this SQL dialect.
     *
     * @param \Cake\Database\Expression\FunctionExpression $expression The function expression
     * to convert to oracle SQL.
     * @return void
     */
    protected function _transformFunctionExpression(FunctionExpression $expression)
    {
        switch ($expression->getName()) {
            case 'CONCAT':
                $expression->setName('')->type(' ||');
                break;
            case 'DATEDIFF':
                $expression
                    ->setName('')
                    ->type('-')
                    ->iterateParts(function ($p) {
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
                    ->type(' + INTERVAL')
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
        }
    }

    /**
     * Get the schema dialect.
     *
     * Used by Cake\Database\Schema package to reflect schema and
     * generate schema.
     *
     * @return \CakeDC\OracleDriver\Database\Schema\OracleSchema
     */
    public function schemaDialect()
    {
        if (!$this->_schemaDialect) {
            $this->_schemaDialect = new OracleSchema($this);
        }
        return $this->_schemaDialect;
    }

    /**
     * {@inheritDoc}
     * @see http://www.dba-oracle.com/t_enabling_disabling_constraints.htm
     */
    public function disableForeignKeySQL()
    {
        return $this->_processAllForeignKeys('disable');
    }

    /**
     * {@inheritDoc}
     * @see http://www.dba-oracle.com/t_enabling_disabling_constraints.htm
     */
    public function enableForeignKeySQL()
    {
        return $this->_processAllForeignKeys('enable');
    }

    /**
     * Get the SQL for enabling or disabling foreign keys
     *
     * @param string $type "enable" or "disable"
     * @return string
     */
    protected function _processAllForeignKeys($type)
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
     * {@inheritDoc}
     *
     * @return \CakeDC\OracleDriver\Database\OracleCompiler
     */
    public function newCompiler()
    {
        return new OracleCompiler;
    }

    /**
     * Transforms an insert query that is meant to insert multiple rows at a time,
     * otherwise it leaves the query untouched.
     *
     * The way Oracle works with multi insert is by having multiple
     * "SELECT FROM DUAL" select statements joined with UNION.
     *
     * @param \Cake\Database\Query $query The query to translate
     * @return \Cake\Database\Query
     */
    protected function _insertQueryTranslator($query)
    {
        $v = $query->clause('values');
        if (count($v->values()) === 1 || $v->query()) {
            return $query;
        }

        $newQuery = $query->connection()->newQuery();
        $cols = $v->columns();
        $placeholder = 0;
        $replaceQuery = false;

        foreach ($v->values() as $k => $val) {
            $fillLength = count($cols) - count($val);
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

            $q = $newQuery->getConnection()->newQuery();
            $newQuery->unionAll($q->select($select)->from('DUAL'));
        }

        if ($replaceQuery) {
            $v->query($newQuery);
        }

        return $query;
    }

}
