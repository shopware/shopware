<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
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
     * @return string|null the fingerprint of the resolved tax rates, or null when the customer group does not derive prices from them
     */
    public static function buildTaxRuleFingerprint(SalesChannelContext $context): ?string
    {
        if ($context->getCurrentCustomerGroup()->getPriceBasis() !== CustomerGroupEntity::PRICE_BASIS_NET) {
            return null;
        }

        $rates = [];
        foreach ($context->getTaxRules() as $tax) {
            $rates[$tax->getId()] = $tax->getRules()?->first()?->getTaxRate() ?? $tax->getTaxRate();
        }

        ksort($rates);

        return Hasher::hash($rates);
    }

    /**
     * @param string[] $areas
     */
    public function getSalesChannelContextHash(SalesChannelContext $context, array $areas = []): string
    {
        $ruleIds = $context->getRuleIdsByAreas($areas);

        $parts = [
            $context->getSalesChannelId(),
            $context->getDomainId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyId(),
            $context->getTaxState(),
            $context->getItemRounding(),
            $ruleIds,
        ];

        $taxRuleFingerprint = self::buildTaxRuleFingerprint($context);
        if ($taxRuleFingerprint !== null) {
            $parts[] = $taxRuleFingerprint;
        }

        return Hasher::hash($parts);
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
            $criteria->getFields(),
            $criteria->getExcludedFields(),
        ]);
    }
}
