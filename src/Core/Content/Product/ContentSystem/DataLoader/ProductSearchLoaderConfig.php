<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductSearchLoaderConfigData array{
 *   searchTermProperty?: non-empty-string,
 *   associations?: list<non-empty-string>,
 *   associationOverride?: non-empty-string
 * }
 *
 * @internal
 */
#[Package('inventory')]
final readonly class ProductSearchLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $searchTermProperty Element property name to read search term from
     * @param list<non-empty-string> $associations
     * @param non-empty-string|null $associationOverride Element property name to read additional associations from
     */
    public function __construct(
        public ?string $searchTermProperty = null,
        public array $associations = [],
        public ?string $associationOverride = null,
    ) {
    }

    /**
     * @return ProductSearchLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->searchTermProperty !== null) {
            $data['searchTermProperty'] = $this->searchTermProperty;
        }

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        if ($this->associationOverride !== null) {
            $data['associationOverride'] = $this->associationOverride;
        }

        return $data;
    }
}
