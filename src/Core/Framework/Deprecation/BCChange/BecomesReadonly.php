<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a property will become readonly in the given version.
 *
 * Code outside the declaring class must stop assigning to the property before the change happens.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BecomesReadonly implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly ?string $description = null,
    ) {
    }
}
