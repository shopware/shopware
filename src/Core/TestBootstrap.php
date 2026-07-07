<?php declare(strict_types=1);

namespace Shopware\Core;

use PHPUnit\TextUI\Configuration\SourceFilter;

require __DIR__ . '/TestBootstrapper.php';

(new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->setEnableCommercial()
    ->bootstrap();

/*
 * Eagerly build PHPUnit's source map (full <source> tree traversal). It is otherwise built
 * lazily while classifying the FIRST triggered deprecation/notice — billing seconds (native fs)
 * to minutes (Docker bind mount) to whichever test happens to trigger it, which poisons the
 * slow-test-detector output with a wandering false entry. @internal API, so fail soft.
 */
try {
    if (!class_exists(SourceFilter::class)) {
        throw new \RuntimeException('class no longer exists — the pre-warm needs to be ported to the current PHPUnit internals');
    }

    SourceFilter::instance();
} catch (\Throwable $e) {
    // pre-warming is an optimization only — warn (the stall would silently return), never break the suite
    fwrite(\STDERR, \sprintf('Could not pre-warm the PHPUnit source map (%s): %s%s', SourceFilter::class, $e->getMessage(), \PHP_EOL));
}
