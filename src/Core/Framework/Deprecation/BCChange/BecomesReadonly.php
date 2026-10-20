<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a property will become readonly in the given version.
 *
 * Code outside the declaring class must stop assigning to the property before the change happens.
 *
 * This attribute intentionally does not apply to classes. When a class is made readonly solely to make
 * its properties readonly, apply this attribute to every affected property instead. A readonly class also
 * changes dynamic-property, untyped/static-property, and inheritance behavior, which requires a separate
 * backwards-compatibility assessment.
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
