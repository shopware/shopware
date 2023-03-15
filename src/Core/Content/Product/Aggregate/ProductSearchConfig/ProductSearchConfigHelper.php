<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ProductSearchConfigHelper
{
    public static function isSearchTermMissing(EntityRepository $productSearchConfigRepository, Context $context, ?string $term = ''): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('languageId', $context->getLanguageId())
        );

        $minTermLength = $productSearchConfigRepository->search($criteria, $context)->first()->getMinSearchLength();

        return \strlen($term) < $minTermLength;
    }
}
