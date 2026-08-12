<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the return type of the method will be narrowed in the given version.
 *
 * Call sites are not affected — the announced type satisfies the current one. Classes overriding
 * the method must declare a return type that is compatible with the announced type before the
 * change happens. Tooling (e.g. Rector) can prepare overrides by reading `$newType`.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ReturnTypeNarrowing implements ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly string $newType,
        public readonly ?string $description = null,
    ) {
    }
}
