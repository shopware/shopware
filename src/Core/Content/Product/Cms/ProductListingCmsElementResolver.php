<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PriceListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\RatingListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeListingFilterHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductListingCmsElementResolver extends AbstractCmsElementResolver
{
    private const FILTER_REQUEST_PARAMS = [
        ManufacturerListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        RatingListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        ShippingFreeListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        PriceListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        PropertyListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute,
        private readonly EntityRepository $sortingRepository
    ) {
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
            $this->addDefaultSorting($request, $slot, $context);
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

    private function addDefaultSorting(Request $request, CmsSlotEntity $slot, SalesChannelContext $context): void
    {
        if ($request->get('order')) {
            return;
        }

        $config = $slot->getTranslation('config');

        if ($config && isset($config['defaultSorting']) && isset($config['defaultSorting']['value']) && $config['defaultSorting']['value']) {
            $defaultSortingValue = $config['defaultSorting']['value'];

            if (!Feature::isActive('v6.7.0.0') && !Uuid::isValid($defaultSortingValue)) {
                Feature::triggerDeprecationOrThrow(
                    'v6.7.0.0',
                    'The sorting key in the product listing CMS element configuration has been replaced with the sorting ID. Please use the sorting ID instead.',
                );

                $request->request->set('order', $defaultSortingValue);

                return;
            }
            $criteria = new Criteria([$defaultSortingValue]);

            $request->request->set('order', $this->sortingRepository->search($criteria, $context->getContext())->first()?->get('key'));

            return;
        }

        // if we have no specific order given at this point, set the order to the highest priority available sorting
        if ($request->get('availableSortings')) {
            $availableSortings = $request->get('availableSortings');
            arsort($availableSortings, \SORT_DESC | \SORT_NUMERIC);
            $sortingId = array_key_first($availableSortings);
            if (!\is_string($sortingId)) {
                return;
            }

            if (!Feature::isActive('v6.7.0.0') && !Uuid::isValid($sortingId)) {
                Feature::triggerDeprecationOrThrow(
                    'v6.7.0.0',
                    'The sorting key in the product listing CMS element configuration has been replaced with the sorting ID. Please use the sorting ID instead.',
                );

                $request->request->set('order', $sortingId);

                return;
            }

            $criteria = new Criteria([$sortingId]);

            $request->request->set('order', $this->sortingRepository->search($criteria, $context->getContext())->first()?->get('key'));
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
        $config = $slot->get('config');

        $enabledFilters = $config['filters']['value'] ?? null;

        $enabledFilters = \is_string($enabledFilters) ? explode(',', $enabledFilters) : self::FILTER_REQUEST_PARAMS;

        $propertyWhitelist = $config['propertyWhitelist']['value'] ?? null ?: null;

        // When the property filters are restricted, they are not in the enabledFilters array
        if (\in_array(PropertyListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM, $enabledFilters, true)
            || !\is_array($propertyWhitelist)) {
            $propertyWhitelist = null;
        }

        $request->request->set(PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM, $propertyWhitelist);

        foreach (self::FILTER_REQUEST_PARAMS as $filterParam) {
            $request->request->set($filterParam, \in_array($filterParam, $enabledFilters, true));
        }
    }
}
