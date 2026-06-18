<?php
declare(strict_types=1);

/**
 * Oracle driver test schema — CakePHP core metadata with Oracle-specific adjustments.
 *
 * @see vendor/cakephp/cakephp/tests/schema.php
 */
$schema = require dirname(__DIR__) . '/vendor/cakephp/cakephp/tests/schema.php';

if (isset($schema['articles_tags']['constraints']['tag_id_fk'])) {
    unset($schema['articles_tags']['constraints']['tag_id_fk']);
}

return $schema;
