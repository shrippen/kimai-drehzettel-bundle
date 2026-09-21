<?php

/*
 * Dependency-free test runner. Usage: php tests/run.php
 * Kimai's PHPUnit is not needed for the pure domain code.
 */

date_default_timezone_set('Europe/Berlin');

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
