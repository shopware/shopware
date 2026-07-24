<?php declare(strict_types=1);

/*
 * Bootstrap for the isolated rector rule test suite.
 *
 * Rector's dist package ships its own copies of nikic/php-parser and phpstan inside
 * vendor/rector/rector/vendor. Rector's AbstractRectorTestCase has to run against exactly
 * those copies, which is why this suite lives in vendor-bin/rector instead of the main
 * phpunit suites (the root vendor directory carries different php-parser/phpstan versions).
 */

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($vendorAutoload)) {
    fwrite(\STDERR, 'Missing ' . $vendorAutoload . \PHP_EOL);
    fwrite(\STDERR, 'Run "composer install -d vendor-bin/rector" first.' . \PHP_EOL);
    exit(1);
}
require $vendorAutoload;

$rectorInnerAutoload = __DIR__ . '/../vendor/rector/rector/vendor/autoload.php';
if (!is_file($rectorInnerAutoload)) {
    fwrite(\STDERR, 'Missing ' . $rectorInnerAutoload . \PHP_EOL);
    fwrite(\STDERR, 'The rector/rector dist package should ship its own vendor directory.' . \PHP_EOL);
    exit(1);
}
require $rectorInnerAutoload;
