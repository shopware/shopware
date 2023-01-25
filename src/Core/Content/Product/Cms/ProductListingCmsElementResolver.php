<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductListingCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractProductListingRoute $listingRoute)
    {
    }

    public function getType(): string
    {
        return 'product-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new ProductListingStruct();
        $slot->setData($data);

        $request = $resolverContext->getRequest();
        $context = $resolverContext->getSalesChannelContext();

        $this->restrictFilters($slot, $request);

        if ($this->isCustomSorting($slot)) {
            $this->restrictSortings($request, $slot);
            $this->addDefaultSorting($request, $slot);
        }

        $navigationId = $this->getNavigationId($request, $context);

        $criteria = new Criteria();
        $criteria->setTitle('cms::product-listing');

        $listing = $this->listingRoute
            ->load($navigationId, $request, $context, $criteria)
            ->getResult();

        $data->setListing($listing);
    }

    private function getNavigationId(Request $request, SalesChannelContext $salesChannelContext): string
    {
        if ($navigationId = $request->get('navigationId')) {
            return $navigationId;
        }

        $params = $request->attributes->get('_route_params');

        if ($params && isset($params['navigationId'])) {
            return $params['navigationId'];
        }

        return $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
    }

    private function isCustomSorting(CmsSlotEntity $slot): bool
    {
        $config = $slot->getTranslation('config');

        if ($config && isset($config['useCustomSorting']) && isset($config['useCustomSorting']['value'])) {
            return $config['useCustomSorting']['value'];
        }

        return false;
    }

    private function addDefaultSorting(Request $request, CmsSlotEntity $slot): void
    {
        if ($request->get('order')) {
            return;
        }

        $config = $slot->getTranslation('config');

        if ($config && isset($config['defaultSorting']) && isset($config['defaultSorting']['value']) && $config['defaultSorting']['value']) {
            $request->request->set('order', $config['defaultSorting']['value']);

            return;
        }

        // if we have no specific order given at this point, set the order to be the highest's priority available sorting
        if ($request->get('availableSortings')) {
            $availableSortings = $request->get('availableSortings');
            arsort($availableSortings, \SORT_DESC | \SORT_NUMERIC);

            $request->request->set('order', array_key_first($availableSortings));
        }
    }

    private function restrictSortings(Request $request, CmsSlotEntity $slot): void
    {
        $config = $slot->getTranslation('config');

        if (!$config || !isset($config['availableSortings']) || !isset($config['availableSortings']['value'])) {
            return;
        }

        $request->request->set('availableSortings', $config['availableSortings']['value']);
    }

    private function restrictFilters(CmsSlotEntity $slot, Request $request): void
    {
        // setup the default behavior
        $defaults = ['manufacturer-filter', 'rating-filter', 'shipping-free-filter', 'price-filter', 'property-filter'];

        $request->request->set(ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM, null);

        $config = $slot->get('config');

        if (isset($config['propertyWhitelist']['value']) && (is_countable($config['propertyWhitelist']['value']) ? \count($config['propertyWhitelist']['value']) : 0) > 0) {
            $request->request->set(ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM, $config['propertyWhitelist']['value']);
        }

        if (!isset($config['filters']['value'])) {
            return;
        }

        // apply config settings
        $config = explode(',', (string) $config['filters']['value']);

        foreach ($defaults as $filter) {
            if (\in_array($filter, $config, true)) {
                continue;
            }

            $request->request->set($filter, false);
        }
    }
}
