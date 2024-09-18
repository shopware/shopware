<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('core')]
class EntityCacheKeyGenerator
{
    public static function buildCmsTag(string $id): string
    {
        return 'cms-page-' . $id;
    }

    public static function buildProductTag(string $id): string
    {
        return 'product-' . $id;
    }

    public static function buildStreamTag(string $id): string
    {
        return 'product-stream-' . $id;
    }

    /**
     * @param string[] $areas
     */
    public function getSalesChannelContextHash(SalesChannelContext $context, array $areas = []): string
    {
        $ruleIds = $context->getRuleIdsByAreas($areas);

        return Hasher::hash([
            $context->getSalesChannelId(),
            $context->getDomainId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyId(),
            $context->getTaxState(),
            $context->getItemRounding(),
            $ruleIds,
        ]);
    }

    public function getCriteriaHash(Criteria $criteria): string
    {
        return Hasher::hash([
            $criteria->getIds(),
            $criteria->getFilters(),
            $criteria->getTerm(),
            $criteria->getPostFilters(),
            $criteria->getQueries(),
            $criteria->getSorting(),
            $criteria->getLimit(),
            $criteria->getOffset() ?? 0,
            $criteria->getTotalCountMode(),
            $criteria->getGroupFields(),
            $criteria->getAggregations(),
            $criteria->getAssociations(),
        ]);
    }
}
