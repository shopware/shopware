<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the class will be marked `final` in the given version.
 *
 * Callers do not need to act. Extension code extending the class must stop doing so before
 * the change happens and should be flagged by tooling.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[Package('framework')]
final class BecomesFinal implements BCChangeAttribute
{
    public function __construct(
        public readonly string $version,
        public readonly ?string $description = null,
    ) {
    }
}
