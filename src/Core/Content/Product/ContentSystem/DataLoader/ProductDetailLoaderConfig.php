<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductDetailLoaderConfigData array{
 *   property?: non-empty-string,
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
#[Package('inventory')]
final readonly class ProductDetailLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read product ID from
     * @param list<non-empty-string> $associations
     */
    public function __construct(
        public ?string $property = null,
        public array $associations = [],
    ) {
    }

    /**
     * @return ProductDetailLoaderConfigData
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

        return $data;
    }
}
