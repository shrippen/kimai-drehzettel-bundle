<?php

/*
 * Dependency-free test runner. Usage: php tests/run.php
 * Kimai's PHPUnit is not needed for the pure domain code.
 */

date_default_timezone_set('Europe/Berlin');

// The date formatting uses intl, like Kimai itself. Some distributions (Arch) install the
// extension but leave it disabled in php.ini: run once more with it loaded instead of
// failing half-way.
if (!extension_loaded('intl')) {
    if (getenv('DREHZETTEL_TESTS_INTL_RETRY') === false) {
        putenv('DREHZETTEL_TESTS_INTL_RETRY=1');
        passthru(escapeshellarg(PHP_BINARY) . ' -d extension=intl ' . escapeshellarg(__FILE__), $code);
        exit($code);
    }
    fwrite(STDERR, "The PHP extension intl is missing; install it or enable it in php.ini.\n");
    exit(2);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'KimaiPlugin\\DrehzettelBundle\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    require dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

$GLOBALS['failures'] = 0;
$GLOBALS['checks'] = 0;

function check(string $name, mixed $expected, mixed $actual): void
{
    $GLOBALS['checks']++;
    if ($expected === $actual) {
        return;
    }
    $GLOBALS['failures']++;
    echo "FAIL $name\n  expected: " . json_encode($expected) . "\n  actual:   " . json_encode($actual) . "\n";
}

foreach (glob(__DIR__ . '/cases/*.php') as $file) {
    echo 'Running ' . basename($file) . "\n";
    require $file;
}

echo "\n{$GLOBALS['checks']} checks, {$GLOBALS['failures']} failures\n";
exit($GLOBALS['failures'] === 0 ? 0 : 1);
