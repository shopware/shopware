<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Enriches a criteria with a product stream's filters and grouping state.
 */
#[Package('inventory')]
abstract class AbstractProductStreamBuilder implements ProductStreamBuilderInterface
{
    abstract public function enrichCriteria(Criteria $criteria, string $id, Context $context): void;

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use enrichCriteria() instead.
     *
     * @return list<Filter>
     */
    public function buildFilters(string $id, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', self::class . '::enrichCriteria')
        );

        $criteria = new Criteria();
        $this->enrichCriteria($criteria, $id, $context);

        return array_values($criteria->getFilters());
    }
}
