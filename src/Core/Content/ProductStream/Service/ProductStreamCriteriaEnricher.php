<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Additive capability for enriching a criteria with a product stream's filters and grouping state.
 *
 * Introduced alongside the deprecation of {@see ProductStreamBuilderInterface}. The concrete
 * ProductStreamBuilder implements this in addition to the deprecated interface, so consumers can
 * detect the capability via `instanceof` and fall back to ProductStreamBuilderInterface::buildFilters()
 * when it is unavailable (e.g. a decorator that has not adopted it yet). This keeps the deprecated
 * interface fully functional and avoids forcing any plugin to migrate before v6.8.0.
 */
#[Package('inventory')]
interface ProductStreamCriteriaEnricher
{
    public const STATE_DISPLAY_AS_GROUP_DISABLED = 'PRODUCT_STREAM_DISPLAY_AS_GROUP_DISABLED';

    public function enrichCriteria(Criteria $criteria, string $id, Context $context): void;
}
