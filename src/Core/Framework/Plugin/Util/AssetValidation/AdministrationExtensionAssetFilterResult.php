<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util\AssetValidation;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class AdministrationExtensionAssetFilterResult
{
    /**
     * @param list<string> $assets
     * @param list<AdministrationExtensionAssetViolation> $violations
     */
    public function __construct(
        public array $assets,
        public array $violations,
    ) {
    }
}
