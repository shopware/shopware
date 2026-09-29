<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use AbstractProductStreamBuilder instead
 */
#[Package('inventory')]
interface ProductStreamBuilderInterface
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed, use AbstractProductStreamBuilder::enrichCriteria instead
     *
     * @return array<int, Filter>
     */
    public function buildFilters(
        string $id,
        Context $context
    ): array;
}
