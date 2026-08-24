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
namespace CakeDC\OracleDriver\Database\Statement\Method;

use CakeDC\OracleDriver\Database\OCI8\OCI8Statement as Statement;

class Oci8Statement extends Statement
{
    /**
     * @inheritDoc
     */
    public function closeCursor(): bool
    {
        if (is_resource($this->_sth)) {
            oci_free_statement($this->_sth);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        if (is_resource($this->_sth)) {
            oci_free_statement($this->_sth);
        }
    }
}
