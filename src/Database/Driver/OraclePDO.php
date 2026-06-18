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
namespace CakeDC\OracleDriver\Database\Driver;

use PDO;

class OraclePDO extends OracleBase
{
    /**
     * @inheritDoc
     */
    protected function createConnection(string $dsn, array $config): PDO
    {
        return $this->createPdo('oci:dbname=' . $dsn, $config);
    }

    /**
     * Returns whether php is able to use this driver for connecting to database.
     *
     * @return bool true if it is valid to use this driver
     */
    public function enabled(): bool
    {
        return class_exists('PDO') && in_array('oci', PDO::getAvailableDrivers(), true);
    }
}
