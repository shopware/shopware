<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Type-hint AbstractProductStreamBuilder. Custom builders should extend it and implement enrichCriteria().
 */
#[Package('inventory')]
interface ProductStreamBuilderInterface
{
    /**
     * @deprecated tag:v6.8.0 - Pass the listing Criteria to AbstractProductStreamBuilder::enrichCriteria() to apply the stream filters and grouping state.
     *
     * @return array<int, Filter>
     */
    public function buildFilters(
        string $id,
        Context $context
    ): array;
}
