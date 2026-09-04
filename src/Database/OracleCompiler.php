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
namespace CakeDC\OracleDriver\Database;

use Cake\Database\Exception\DatabaseException;
use Cake\Database\Expression\AggregateExpression;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\ExpressionInterface;
use Cake\Database\Query;
use Cake\Database\QueryCompiler;
use Cake\Database\ValueBinder;
use CakeDC\OracleDriver\Database\Driver\OracleBase;

class OracleCompiler extends QueryCompiler
{
    /**
     * {@inheritDoc}
     *
     * @var array<string>
     */
    protected array $_selectParts = [
        'comment',
        'with',
        'select',
        'from',
        'join',
        'where',
        'group',
        'having',
        'window',
        'order',
        'limit',
        'offset',
        'union',
        'except',
        'epilog',
        'intersect',
    ];

    /**
     * Always quote aliases in SELECT clause.
     *
     * Oracle auto converts unquoted identifiers to upper case.
     *
     * @var bool
     */
    protected bool $_quotedSelectAliases = true;

    /**
     * {@inheritDoc}
     *
     * @var array<string, string>
     */
    protected array $_templates = [
        'delete' => 'DELETE',
        'where' => ' WHERE %s',
        'group' => ' GROUP BY %s',
        'having' => ' HAVING %s',
        'order' => ' %s',
        'limit' => ' LIMIT %s',
        'offset' => ' OFFSET %s',
        'epilog' => ' %s',
        'comment' => '/* %s */ ',
    ];

    /**
     * Compile the query after ensuring GROUP BY completeness (ORA-00979 rescue).
     *
     * @param \Cake\Database\Query $query The query that is being compiled
     * @param \Cake\Database\ValueBinder $binder Value binder used to generate parameter placeholders
     * @return string
     */
    public function compile(Query $query, ValueBinder $binder): string
    {
        $this->_ensureGroupByCompleteness($query);

        return parent::compile($query, $binder);
    }

    /**
     * Oracle (like Postgres) enforces strict GROUP BY: every column referenced
     * in the SELECT list outside an aggregate must appear in GROUP BY.
     * Core `SelectLoader::_subqueryFields()` can emit subqueries that violate
     * this (binding keys grouped + ORDER BY/HAVING alias selected, e.g.
     * ORA-00979). Since the subquery groups by the primary key, the extra
     * columns are functionally dependent, so appending the bare columns to
     * GROUP BY is safe and does not change the result set.
     *
     * Only bare column references are appended (including columns nested
     * inside scalar functions such as `LENGTH(name)`); aggregates and nested
     * subqueries are left alone.
     *
     * @param \Cake\Database\Query $query The query being compiled.
     * @return void
     */
    protected function _ensureGroupByCompleteness(Query $query): void
    {
        if ($query->type() !== 'select') {
            return;
        }
        $group = $query->clause('group');
        if (empty($group)) {
            return;
        }
        $select = $query->clause('select');
        if (empty($select)) {
            return;
        }
        $grouped = [];
        foreach ((array)$group as $g) {
            $sql = $g instanceof ExpressionInterface ? $g->sql(new ValueBinder()) : (string)$g;
            $grouped[static::_normalizeColumnRef($sql)] = true;
        }

        $columns = [];
        foreach ($select as $expr) {
            static::_collectBareColumns($expr, $columns);
        }
        foreach ((array)$group as $g) {
            static::_collectBareColumns($g, $columns);
        }
        $missing = [];
        foreach ($columns as $norm => $ref) {
            if (!isset($grouped[$norm])) {
                $missing[] = $ref;
                $grouped[$norm] = true;
            }
        }
        if ($missing) {
            $query->groupBy($missing);
        }
    }

    /**
     * Normalizes a column reference for comparison (case/quoting insensitive).
     *
     * @param string $ref Column reference or SQL fragment.
     * @return string
     */
    protected static function _normalizeColumnRef(string $ref): string
    {
        return strtolower((string)preg_replace('/["`\s\[\]]/', '', $ref));
    }

    /**
     * Collects bare column references used outside aggregates and subqueries.
     *
     * Identifiers nested inside an `AggregateExpression` need no grouping, and
     * identifiers inside a nested `Query` belong to the inner query scope, so
     * both are excluded. Windowed expressions (`OVER ...`) are evaluated after
     * grouping and are excluded as well.
     *
     * @param mixed $expr Select/group expression to inspect.
     * @param array<string, string> $columns Collected `normalized => original ref` map.
     * @return void
     */
    protected static function _collectBareColumns(mixed $expr, array &$columns): void
    {
        if ($expr instanceof Query || $expr instanceof AggregateExpression) {
            return;
        }
        if ($expr instanceof IdentifierExpression) {
            $ref = $expr->getIdentifier();
            $columns[static::_normalizeColumnRef($ref)] = $ref;

            return;
        }
        if ($expr instanceof ExpressionInterface) {
            $scopes = [];
            $expr->traverse(function ($node) use (&$scopes): void {
                if ($node instanceof Query || $node instanceof AggregateExpression) {
                    $scopes[] = $node;
                }
            });
            $excluded = [];
            foreach ($scopes as $scope) {
                $scope->traverse(function ($node) use (&$excluded): void {
                    if ($node instanceof IdentifierExpression) {
                        $excluded[static::_normalizeColumnRef($node->getIdentifier())] = true;
                    }
                });
            }

            $windowed = stripos($expr->sql(new ValueBinder()), ' OVER') !== false;
            $expr->traverse(function ($node) use (&$columns, $excluded, $windowed): void {
                if (
                    !$windowed &&
                    $node instanceof IdentifierExpression &&
                    !isset($excluded[static::_normalizeColumnRef($node->getIdentifier())])
                ) {
                    $ref = $node->getIdentifier();
                    $columns[static::_normalizeColumnRef($ref)] = $ref;
                }
            });

            return;
        }
        if (is_string($expr)) {
            $t = trim($expr);
            if ($t === '' || $t === '*' || is_numeric($t)) {
                return;
            }
            if (preg_match('/^\s*\(?\s*SELECT\b/i', $t)) {
                return;
            }
            if (str_contains($t, '(')) {
                return;
            }
            if (preg_match('/^[\w."\`\[\]]+$/', $t)) {
                $columns[static::_normalizeColumnRef($t)] = $t;
            }
        }
    }

    /**
     * Oracle has no `WITH RECURSIVE` keyword; recursive CTEs use plain `WITH`.
     *
     * @param array $parts List of CTEs to be transformed to string
     * @param \Cake\Database\Query $query The query that is being compiled
     * @param \Cake\Database\ValueBinder $binder Value binder used to generate parameter placeholder
     * @return string
     */
    protected function _buildWithPart(array $parts, Query $query, ValueBinder $binder): string
    {
        $expressions = [];
        foreach ($parts as $cte) {
            $expressions[] = $cte->sql($binder);
        }

        return sprintf('WITH %s ', implode(', ', $expressions));
    }

    /**
     * Oracle spells `EXCEPT` as `MINUS`.
     *
     * @param array $parts list of queries to be operated with EXCEPT
     * @param \Cake\Database\Query $query The query that is being compiled
     * @param \Cake\Database\ValueBinder $binder Value binder used to generate parameter placeholder
     * @return string
     */
    protected function _buildExceptPart(array $parts, Query $query, ValueBinder $binder): string
    {
        return $this->_buildSetOperationPart('MINUS', $parts, $query, $binder);
    }

    /**
     * Builds the SQL fragment for INSERT INTO.
     *
     * @param array $parts The insert parts.
     * @param \Cake\Database\Query $query The query that is being compiled
     * @param \Cake\Database\ValueBinder $generator the placeholder generator to be used in expressions
     * @return string SQL fragment.
     */
    protected function _buildInsertPart(array $parts, Query $query, ValueBinder $generator): string
    {
        $driver = $query->getConnection()->getDriver();
        if (!$driver instanceof OracleBase) {
            throw new DatabaseException('Oracle compiler requires an OracleBase driver');
        }

        $table = $driver->quoteIfAutoQuote($parts[0]);
        $columns = $this->_stringifyExpressions($parts[1], $generator);
        $modifiers = $this->_buildModifierPart($query->clause('modifier'), $query, $generator);

        return sprintf('INSERT%s INTO %s (%s)', $modifiers, $table, implode(', ', $columns));
    }
}
