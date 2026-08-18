<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the class keeps working unchanged but relocates to a different fully-qualified
 * name in the given version, for example when it moves into another namespace.
 *
 * Any third-party reference to the current name — calling, extending, or type-hinting — must be
 * updated to the new location before the change happens. The new class does not exist yet, so
 * `$newLocation` is given as a plain FQCN string rather than a `::class` constant.
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
