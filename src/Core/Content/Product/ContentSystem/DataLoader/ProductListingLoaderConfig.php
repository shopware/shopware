<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductListingLoaderConfigData array{
 *   property?: non-empty-string,
 *   associations?: list<non-empty-string>,
 *   aggregations?: bool
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class ProductListingLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read navigation ID from
     * @param list<non-empty-string> $associations
     * @param bool $aggregations Whether the element renders filters and therefore needs the aggregations
     */
    public function __construct(
        public ?string $property = null,
        public array $associations = [],
        public bool $aggregations = true
    ) {
    }

    /**
     * @return ProductListingLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->property !== null) {
            $data['property'] = $this->property;
        }

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        if ($this->aggregations === false) {
            $data['aggregations'] = false;
        }

        return $data;
    }
}
