<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type BreadcrumbLoaderConfigData array{
 *   property?: non-empty-string,
 *   type?: non-empty-string,
 *   referrerCategoryProperty?: non-empty-string
 * }
 *
 * @internal
 */
#[Package('inventory')]
final readonly class BreadcrumbLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read entity ID from
     * @param non-empty-string $type Breadcrumb type: 'product' or 'category'
     * @param non-empty-string|null $referrerCategoryProperty Element property name to read referrer category ID from
     */
    public function __construct(
        public ?string $property = null,
        public string $type = 'product',
        public ?string $referrerCategoryProperty = null,
    ) {
    }
}
