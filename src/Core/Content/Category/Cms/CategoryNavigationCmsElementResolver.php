<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Category\Cms;

use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNavigationCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NavigationLoaderInterface $navigationLoader,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getType(): string
    {
        return 'category-navigation';
    }

    /**
     * @codeCoverageIgnore
     */
    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $salesChannelContext = $resolverContext->getSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();

        $rootNavigationId = $salesChannel->getNavigationCategoryId();
        $navigationId = $resolverContext->getRequest()->get('navigationId', $rootNavigationId);

        $tree = $this->navigationLoader->load(
            $navigationId,
            $salesChannelContext,
            $rootNavigationId,
            $salesChannel->getNavigationCategoryDepth()
        );

        $slot->setData($tree);
    }
}
