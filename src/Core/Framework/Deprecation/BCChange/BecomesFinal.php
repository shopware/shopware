<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the class will be marked `final` in the given version.
 *
 * Call sites are not affected. Classes extending the annotated class must stop doing so before
 * the change happens, e.g. by switching to composition or the intended extension point.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[Package('framework')]
final class BecomesFinal implements ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly ?string $description = null,
    ) {
    }
}
