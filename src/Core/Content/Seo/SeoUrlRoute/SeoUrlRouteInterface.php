<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

interface SeoUrlRouteInterface
{
    public function getConfig(): SeoUrlRouteConfig;

    public function prepareCriteria(Criteria $criteria): void;

    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping;

    /**
     * @deprecated tag:v6.3.0 - The update detection is moved to the corresponding indexer classes
     */
    public function extractIdsToUpdate(EntityWrittenContainerEvent $event): SeoUrlExtractIdResult;

    /**
     * @deprecated tag:v6.3.0 - Has no more usage
     */
    public function getSeoVariables(): array;
}
