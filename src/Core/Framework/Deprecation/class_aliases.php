<?php declare(strict_types=1);

use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;

/**
 * Runs while Composer initializes, before PHPUnit can start collecting coverage.
 *
 * @codeCoverageIgnore
 */
// @codeCoverageIgnoreStart
foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
    if (class_exists($previousClassName, autoload: false)) {
        continue;
    }

    class_alias($currentClassName, $previousClassName);
}
// @codeCoverageIgnoreEnd
