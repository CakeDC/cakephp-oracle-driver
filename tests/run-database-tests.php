<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CakeDC\OracleDriver\Test\TestCase\DatabaseSuite;

$phpunit = escapeshellarg(__DIR__ . '/../vendor/bin/phpunit');
$configPath = file_exists(__DIR__ . '/../phpunit.xml')
    ? __DIR__ . '/../phpunit.xml'
    : __DIR__ . '/../phpunit.xml.dist';
$config = escapeshellarg($configPath);

$extra = '';
foreach (array_slice($argv, 1) as $arg) {
    $extra .= ' ' . escapeshellarg($arg);
}

$exitCode = 0;

foreach (DatabaseSuite::PERMUTATIONS as $label => $enabled) {
    fwrite(STDOUT, PHP_EOL . '=== ' . $label . ' ===' . PHP_EOL);
    putenv('ORACLE_IDENTIFIER_QUOTING=' . ($enabled ? '1' : '0'));
    passthru('php ' . $phpunit . ' -c ' . $config . $extra, $runExitCode);
    if ($runExitCode !== 0) {
        $exitCode = $runExitCode;
    }
}

exit($exitCode);
