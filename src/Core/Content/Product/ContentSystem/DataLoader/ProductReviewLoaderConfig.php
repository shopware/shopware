<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductReviewLoaderConfigData array{
 *   property?: non-empty-string
 * }
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class ProductReviewLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read the product ID from
     */
    public function __construct(
        public ?string $property = null,
    ) {
    }

    /**
     * @return ProductReviewLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->property !== null) {
            $data['property'] = $this->property;
        }

        return $data;
    }
}
