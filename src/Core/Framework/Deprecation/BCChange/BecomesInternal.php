<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the class or method will be marked `@internal` in the given version.
 *
 * The functionality itself is not deprecated and keeps working, but it will no longer be part
 * of the backwards-compatibility promise. Extension code referencing the symbol should be
 * flagged by tooling so authors can stop relying on it before the change happens.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[Package('framework')]
final class BecomesInternal implements BCChangeAttribute
{
    public function __construct(
        public readonly string $version,
        public readonly ?string $description = null,
    ) {
    }
}
