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

use Cake\Database\Query;
use Cake\Database\QueryCompiler;
use Cake\Database\Exception\DatabaseException;
use Cake\Database\ValueBinder;

class Oracle12Compiler extends QueryCompiler
{
    /**
     * {@inheritDoc}
     *
     * @var array<string, string>
     */
    protected array $_templates = [
        'delete' => 'DELETE',
        'where' => ' WHERE %s',
        'group' => ' GROUP BY %s ',
        'having' => ' HAVING %s ',
        'order' => ' %s',
        'offset' => ' OFFSET %s ROWS ',
        'limit' => ' FETCH NEXT %s ROWS ONLY ',
        'epilog' => ' %s',
        'comment' => '/* %s */ ',
    ];

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
        'offset',
        'limit',
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
     * Builds the SQL fragment for INSERT INTO.
     *
     * @param array $parts The insert parts.
     * @param \Cake\Database\Query $query The query that is being compiled
     * @param \Cake\Database\ValueBinder $generator the placeholder generator to be used in expressions
     * @return string SQL fragment.
     */
    protected function _buildInsertPart(array $parts, Query $query, ValueBinder $generator): string
    {
        if (!isset($parts[0])) {
            throw new DatabaseException(
                'Could not compile insert query. No table was specified. ' .
                'Use `into()` to define a table.'
            );
        }
        
        $driver = $query->getConnection()->getDriver();
        $table = $driver->quoteIfAutoQuote($parts[0]);
        $columns = $this->_stringifyExpressions($parts[1], $generator);
        $modifiers = $this->_buildModifierPart($query->clause('modifier'), $query, $generator);

        return sprintf('INSERT%s INTO %s (%s)', $modifiers, $table, implode(', ', $columns));
    }
}
