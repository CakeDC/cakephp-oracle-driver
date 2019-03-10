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

/**
 * Statement class meant to be used by an Oracle driver
 *
 */
class OracleBufferedStatement extends BufferedStatement
{

    /**
     * {@inheritDoc}
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
            foreach ($record as $key => &$value) {
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }
            }
        }
        $this->buffer[] = $record;

        return $record;

    }

}
