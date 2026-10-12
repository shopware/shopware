<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the class or method will be marked `@internal` in the given version.
 *
 * The functionality keeps working, but it leaves the backwards-compatibility promise: any
 * third-party reference — calling, extending, or type-hinting — relies on unsupported API from
 * the announced version on and must be reconsidered before the change happens.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class BecomesInternal implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly ?string $description = null,
    ) {
    }
}
