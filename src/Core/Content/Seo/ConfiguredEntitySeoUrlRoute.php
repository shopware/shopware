<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

use function Symfony\Component\String\u;

/**
 * @internal
 */
#[Package('inventory')]
class ConfiguredEntitySeoUrlRoute extends ConfiguredSeoUrlRoute
{
    public function __construct(
        private readonly EntitySeoUrlRouteInterface $decorated,
    ) {
        parent::__construct($this, $decorated->getConfig());
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $this->decorated->prepareCriteria($criteria, $salesChannel);
    }

    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if ($this->decorated instanceof SeoUrlRouteInterface) {
            return $this->decorated->getMapping($entity, $salesChannel);
        }

        // Fallback for config-only routes: expose the entity in the template under its entity name.
        $serialized = $entity->jsonSerialize();

        if ($entity instanceof CategoryEntity) {
            $serialized['seoBreadcrumb'] = CategoryBreadcrumbHelper::build($entity, $salesChannel);
        }

        return new SeoUrlMapping(
            $entity,
            $this->getConfig()->getPrimaryKeyParameter($entity->getUniqueIdentifier()),
            [u($this->getConfig()->getDefinition()->getEntityName())->camel()->toString() => $serialized]
        );
    }
}
