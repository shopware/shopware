<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * Configuration for shipping method data loader.
 *
 * @phpstan-type ShippingMethodLoaderConfigData array{
 *   associations?: list<non-empty-string>,
 *   onlyAvailable?: bool
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class ShippingMethodLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param list<non-empty-string> $associations Additional associations to load
     * @param bool $onlyAvailable Only return available shipping methods (default: true)
     */
    public function __construct(
        public array $associations = [],
        public bool $onlyAvailable = true,
    ) {
    }
}
