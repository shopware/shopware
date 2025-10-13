<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderConfigInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductListingLoaderConfigData array{
 *   limit?: positive-int,
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
#[Package('discovery')]
final readonly class ProductListingLoaderConfig implements ContentDataLoaderConfigInterface
{
    /**
     * @param positive-int|null $limit
     * @param list<non-empty-string> $associations
     */
    public function __construct(
        public ?int $limit,
        public array $associations
    ) {
    }
}
