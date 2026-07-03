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
if (class_exists(SourceFilter::class)) {
    try {
        SourceFilter::instance();
    } catch (\Throwable) {
        // pre-warming is an optimization only — never break the suite over it
    }
}
