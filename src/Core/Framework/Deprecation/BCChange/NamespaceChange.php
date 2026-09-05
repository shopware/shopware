<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the class will move to a different namespace in the given version.
 *
 * `$newLocation` is the fully qualified class name at its new location; the class name itself
 * stays the same, only the namespace around it changes.
 *
 * The class keeps working as-is and there is nothing to migrate to yet, but any third-party
 * reference by its fully-qualified name — importing, type-hinting, instantiating,
 * `instanceof`-checking, or extending it — has to be updated to `$newLocation` before the
 * announced version.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NamespaceChange implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly string $newLocation,
        public readonly ?string $description = null,
    ) {
    }
}
