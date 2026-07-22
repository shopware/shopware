<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the return type of the method will be widened in the given version.
 *
 * Call sites must be prepared to handle every value of the announced type before the change
 * happens — for example a return type that becomes nullable. Classes overriding the method are
 * not affected: their narrower return type stays valid (covariance).
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ReturnTypeWidening implements CallSiteCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly string $newType,
        public readonly ?string $description = null,
    ) {
    }
}
