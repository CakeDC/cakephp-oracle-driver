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
        'select',
        'from',
        'join',
        'where',
        'group',
        'having',
        'order',
        'union',
        'epilog',
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
        'order' => ' %s',
        'limit' => ' LIMIT %s',
        'offset' => ' OFFSET %s',
        'epilog' => ' %s',
        'comment' => '/* %s */ ',
    ];

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
