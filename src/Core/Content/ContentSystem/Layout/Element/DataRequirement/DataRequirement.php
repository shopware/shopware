<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingDataLoader;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type EntityLoaderConfig from EntityLoader
 * @phpstan-import-type EntityCollectionLoaderConfig from EntityCollectionLoader
 * @phpstan-import-type ProductListingLoaderConfig from ProductListingDataLoader
 *
 * @internal
 */
#[Package('discovery')]
readonly class DataRequirement
{
    /**
     * @param EntityLoaderConfig|EntityCollectionLoaderConfig|ProductListingLoaderConfig $config
     */
    public function __construct(
        public string $key,
        public string $source,
        public array $config = []
    ) {
    }
}
