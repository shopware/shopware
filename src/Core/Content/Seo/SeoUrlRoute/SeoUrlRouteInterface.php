<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

interface SeoUrlRouteInterface
{
    public function getConfig(): SeoUrlRouteConfig;

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_13410) Parameter $salesChannel will be required
     */
    public function prepareCriteria(Criteria $criteria/*, SalesChannelEntity $salesChannel */): void;

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_13410) Parameter $salesChannel will be required
     */
    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping;
}
