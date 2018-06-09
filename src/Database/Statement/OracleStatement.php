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

namespace CakeDC\OracleDriver\Database\Statement;

use Cake\Database\Statement\BufferedStatement;
use Cake\Database\Statement\BufferResultsTrait;
use Cake\Database\Statement\StatementDecorator;

/**
 * Statement class meant to be used by an Oracle driver
 */
class OracleStatement extends StatementDecorator
{

    use BufferResultsTrait;

    public $queryString;

    public $paramMap;

    /**
     * {@inheritDoc}
     */
    public function execute($params = null)
    {
        if ($this->_statement instanceof BufferedStatement) {
            $this->_statement = $this->_statement->getInnerStatement();
        }

        if ($this->_bufferResults) {
            $this->_statement = new OracleBufferedStatement($this->_statement, $this->_driver);
        }

        return $this->_statement->execute($params);
    }

    /**
     * {@inheritDoc}
     */
    public function __get($property)
    {
        if ($property === 'queryString') {
            return empty($this->queryString) ? $this->_statement->queryString : $this->queryString;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function bind($params, $types)
    {
        if (empty($params)) {
            return;
        }

        $annonymousParams = is_int(key($params)) ? true : false;

        $offset = 0;

        foreach ($params as $index => $value) {
            $type = null;
            if (isset($types[$index])) {
                $type = $types[$index];
            }
            if ($annonymousParams) {
                $index += $offset;
            }
            $this->bindValue($index, $value, $type);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function bindValue($column, $value, $type = 'string')
    {
        $column = isset($this->paramMap[$column]) ? $this->paramMap[$column] : $column;

        $type = $type == 'boolean' ? 'integer' : $type;

        $this->_statement->bindValue($column, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function fetch($type = 'num')
    {
        $result = $this->_statement->fetch($type);
        if (is_array($result)) {

            //Revert shortened identifiers to original name
            if ($type == 'assoc' && !empty($this->_driver->autoShortenedIdentifiers)) {
                //Need to preserve order of row results
                $translatedRow = [];

                foreach ($result as $key => $value) {
                    if (array_key_exists($key, $this->_driver->autoShortenedIdentifiers)) {
                        $translatedRow[$this->_driver->autoShortenedIdentifiers[$key]] = $value;
                    } else {
                        $translatedRow[$key] = $value;
                    }
                }

                $result = $translatedRow;
            }

            foreach ($result as $key => &$value) {
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }
            }
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll($type = 'num')
    {
        return $this->_statement->fetchAll($type);
    }

}