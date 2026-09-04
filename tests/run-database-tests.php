<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CakeDC\OracleDriver\Test\TestCase\DatabaseSuite;

/**
 * Checks whether the installed PHPUnit binary lists the given CLI option
 * in its help output. Display flags such as `--display-phpunit-notices`
 * only exist on newer PHPUnit majors (PHP 8.2 jobs resolve PHPUnit 11,
 * which exits with code 2 on unknown options), so unsupported flags are
 * skipped instead of failing the run.
 *
 * @param string $phpunit Escaped path to the PHPUnit binary.
 * @param string $option CLI option including the leading `--`.
 * @return bool
 */
function phpunitSupportsOption(string $phpunit, string $option): bool
{
    static $help = null;
    $help ??= (string)shell_exec('php ' . $phpunit . ' --help 2>&1');

    return $help !== '' && str_contains($help, $option);
}

$phpunit = escapeshellarg(__DIR__ . '/../vendor/bin/phpunit');
$configPath = file_exists(__DIR__ . '/../phpunit.xml')
    ? __DIR__ . '/../phpunit.xml'
    : __DIR__ . '/../phpunit.xml.dist';
$config = escapeshellarg($configPath);

$extra = '';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--display-') && !phpunitSupportsOption($phpunit, $arg)) {
        fwrite(STDERR, sprintf('Skipping unsupported PHPUnit option "%s".' . PHP_EOL, $arg));
        continue;
    }

    $extra .= ' ' . escapeshellarg($arg);
}

$exitCode = 0;

foreach (DatabaseSuite::PERMUTATIONS as $label => $enabled) {
    $enabled = true;
    fwrite(STDOUT, PHP_EOL . '=== ' . $label . ' ===' . PHP_EOL);
    putenv('ORACLE_IDENTIFIER_QUOTING=' . ($enabled ? '1' : '0'));
    passthru('php ' . $phpunit . ' -c ' . $config . $extra, $runExitCode);
    if ($runExitCode !== 0) {
        $exitCode = $runExitCode;
    }
}

exit($exitCode);
