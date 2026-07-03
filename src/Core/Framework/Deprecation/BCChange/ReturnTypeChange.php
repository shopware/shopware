<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the return type of the method will change in the given version.
 *
 * Callers do not need to act. Classes overriding the method must ensure their return type
 * stays compatible with the announced type. Tooling (e.g. Rector) can prepare call sites
 * and overrides by reading `$newType`.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
#[Package('framework')]
final class ReturnTypeChange implements BCChangeAttribute
{
    public function __construct(
        public readonly string $version,
        public readonly string $newType,
        public readonly ?string $description = null,
    ) {
    }
}
