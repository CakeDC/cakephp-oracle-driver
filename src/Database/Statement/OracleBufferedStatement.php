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
namespace CakeDC\OracleDriver\Database\Statement;

use Cake\Database\Statement\BufferedStatement;
use CakeDC\OracleDriver\Database\Driver\OracleOCI;

/**
 * Statement class meant to be used by an Oracle driver
 */
class OracleBufferedStatement extends BufferedStatement
{
    /**
     * @inheritDoc
     *
     * Emulate fetchAll using loop over fetch for Oracle PDO to fix issue with fetching wrong CLOB
     */
    public function fetchAll($type = self::FETCH_TYPE_NUM)
    {
        if ($this->_driver instanceof OracleOCI) {
            return parent::fetchAll($type);
        }

        if ($this->_allFetched) {
            return $this->buffer;
        }

        while (!$this->_allFetched) {
            $this->fetch($type);
        }

        return $this->buffer;
    }

    /**
     * @inheritDoc
     */
    public function fetch($type = self::FETCH_TYPE_NUM)
    {
        if ($this->_allFetched) {
            $row = false;
            if (isset($this->buffer[$this->index])) {
                $row = $this->buffer[$this->index];
            }

            $this->index += 1;

            if ($row && $type === static::FETCH_TYPE_NUM) {
                return array_values($row);
            }

            return $row;
        }

        $record = $this->statement->fetch($type);

        if ($record === false) {
            $this->_allFetched = true;
            $this->statement->closeCursor();

            return false;
        }

        if (is_array($record)) {
            foreach ($record as &$value) {
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }
            }
        }

        $this->buffer[] = $record;

        return $record;
    }
}
