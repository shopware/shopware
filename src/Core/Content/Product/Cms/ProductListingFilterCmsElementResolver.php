<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Struct\ArrayEntity;

class ProductListingFilterCmsElementResolver extends AbstractCmsElementResolver
{
    public function getType(): string
    {
        return 'sidebar-filter';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $params = $resolverContext->getRequest()->attributes->get('_route_params');

        if (!$params) {
            return;
        }

        if (!isset($params['navigationId'])) {
            return;
        }

        $data = new ArrayEntity([
            'navigationId' => $params['navigationId'],
        ]);

        $slot->setData($data);
    }
}
