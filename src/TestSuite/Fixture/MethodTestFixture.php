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
namespace CakeDC\OracleDriver\TestSuite\Fixture;

use Cake\Core\Exception\CakeException;
use Cake\Datasource\ConnectionInterface;
use Cake\Log\Log;
use Cake\Utility\Inflector;
use Exception;

/**
 * Cake TestFixture is responsible for building and destroying code objects (triggers, procedures, functions and packages) to be used
 * during testing.
 */
class MethodTestFixture
{
    /**
     * Fixture Datasource
     *
     * @var string
     */
    public string $connection = 'test';

    /**
     * Name
     *
     * @var string
     */
    public string $name;

    /**
     * The plain pl/sql code blocks to create object.
     *
     * @var array
     */
    public array $create = [];

    /**
     * The plain pl/sql code blocks to drop object.
     *
     * @var array
     */
    public array $drop = [];

    /**
     * Instantiate the fixture.
     *
     * @throws \Cake\Core\Exception\Exception on invalid datasource usage.
     */
    public function __construct()
    {
        if (!empty($this->connection)) {
            $connection = $this->connection;
            if (!str_starts_with($connection, 'test')) {
                $message = sprintf(
                    'Invalid datasource name "%s" for "%s" fixture. Fixture datasource names must begin with "test".',
                    $connection,
                    $this->name,
                );
                throw new CakeException($message);
            }
        }

        $this->init();
    }

    /**
     * @inheritDoc
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * @inheritDoc
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Initialize the fixture.
     *
     * @return void
     * @throws \Cake\ORM\Exception\MissingTableClassException When importing from a table that does not exist.
     */
    public function init(): void
    {
        if ($this->name === null) {
            [, $class] = namespaceSplit(static::class);
            preg_match('/^(.*)MethodFixture$/', $class, $matches);
            $method = $class;
            if (isset($matches[1])) {
                $method = $matches[1];
            }

            $this->name = Inflector::tableize($method);
        }
    }

    /**
     * @inheritDoc
     */
    public function create(ConnectionInterface $db): bool
    {
        try {
            $queries = [];
            if ($this->create !== []) {
                $queries = (array)$this->create;
            }

            foreach ($queries as $query) {
                $statement = $db->getDriver()->prepare($query);
                $statement->execute();
                $statement->closeCursor();
            }
        } catch (Exception $exception) {
            $msg = sprintf(
                'Fixture creation for "%s" failed "%s"',
                $this->name,
                $exception->getMessage(),
            );
            Log::error($msg);
            trigger_error($msg, E_USER_WARNING);

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function drop(ConnectionInterface $db): bool
    {
        try {
            $sql = [];
            if ($this->drop !== null) {
                $sql = (array)$this->drop;
            }

            foreach ($sql as $query) {
                $statement = $db->getDriver()->prepare($query);
                $statement->execute();
                $statement->closeCursor();
            }
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function insert(ConnectionInterface $db): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function createConstraints(ConnectionInterface $db): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function dropConstraints(ConnectionInterface $db): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function truncate(ConnectionInterface $db): bool
    {
        return true;
    }
}
