<?php declare(strict_types=1);

use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;

/**
 * Runs while Composer initializes, before PHPUnit can start collecting coverage.
 */
// @codeCoverageIgnoreStart
foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
    // @phpstan-ignore function.impossibleType (PHPStan does not account for aliases registered in this loop.)
    if (!class_exists($previousClassName, autoload: false)) {
        class_alias($currentClassName, $previousClassName);
    }
}
// @codeCoverageIgnoreEnd
