<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductListingLoaderConfigData array{
 *   property?: non-empty-string,
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
#[Package('discovery')]
final readonly class ProductListingLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @codeCoverageIgnore
     *
     * @param non-empty-string|null $property Element property name to read navigation ID from
     * @param list<non-empty-string> $associations
     */
    public function __construct(
        public ?string $property = null,
        public array $associations = []
    ) {
    }
}
