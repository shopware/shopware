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
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
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
            $allSortingOptions = $this->getAllActiveSortingOptions($context);
            $this->restrictSortings($request, $slot, $allSortingOptions);
            $this->addDefaultSorting($request, $slot, $allSortingOptions);
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

    private function getAllActiveSortingOptions(SalesChannelContext $context): ProductSortingCollection
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('priority', 'DESC'));

        /** @var ProductSortingCollection $collection */
        $collection = $this->sortingRepository->search($criteria, $context->getContext())->getEntities();

        return $collection;
    }

    private function isCustomSorting(CmsSlotEntity $slot): bool
    {
        $config = $slot->getTranslation('config');

        return $config['useCustomSorting']['value'] ?? false;
    }

    private function addDefaultSorting(Request $request, CmsSlotEntity $slot, ProductSortingCollection $sortCollection): void
    {
        if ($request->get('order')) {
            return;
        }

        $config = $slot->getTranslation('config');

        if (isset($config['defaultSorting']['value'])) {
            $defaultSortingValue = $config['defaultSorting']['value'];
            foreach ($sortCollection as $sorting) {
                if ($sorting->getId() === $defaultSortingValue) {
                    $request->request->set('order', $sorting->getKey());

                    return;
                }
            }
        }

        $restrictedProductSortingCollection = $request->get('restrictedProductSortingCollection');
        if ($restrictedProductSortingCollection && \is_array($restrictedProductSortingCollection)) {
            $firstSorting = reset($restrictedProductSortingCollection);
            if (\is_object($firstSorting) && method_exists($firstSorting, 'getKey')) {
                $request->request->set('order', $firstSorting->getKey());
            }
        }
    }

    private function restrictSortings(Request $request, CmsSlotEntity $slot, ProductSortingCollection $sortCollection): void
    {
        $config = $slot->getTranslation('config');

        if (!$config || !isset($config['availableSortings']) || !isset($config['availableSortings']['value'])) {
            return;
        }

        $customSortingIds = $config['availableSortings']['value'];
        $customSortings = [];

        foreach ($sortCollection as $sorting) {
            foreach ($customSortingIds as $customSortingId => $customSortingPriority) {
                if ($sorting->getId() === $customSortingId) {
                    $customSortings[$customSortingId] = [
                        'entity' => $sorting,
                        'priority' => $customSortingPriority,
                    ];
                }
            }
        }

        uasort($customSortings, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        $customSorting = new ProductSortingCollection();
        foreach ($customSortings as $data) {
            $customSorting->add($data['entity']);
        }

        /** @deprecated tag:v6.8.0 - The availableSortings request parameter will be removed. Use restrictedProductSortingCollection instead. */
        $request->request->set('availableSortings', $config['availableSortings']['value']);
        $request->attributes->set('restrictedProductSortingCollection', $customSorting);
    }

    private function restrictFilters(CmsSlotEntity $slot, Request $request): void
    {
        $config = $slot->get('config');

        $enabledFilters = $config['filters']['value'] ?? null;

        $enabledFilters = \is_string($enabledFilters) ? explode(',', $enabledFilters) : self::FILTER_REQUEST_PARAMS;

        $propertyWhitelist = $config['propertyWhitelist']['value'] ?? null ?: null;

        // When the property filters are restricted, they are not in the enabledFilters array
        if (!\is_array($propertyWhitelist)
            || \in_array(PropertyListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM, $enabledFilters, true)) {
            $propertyWhitelist = null;
        }

        $request->request->set(PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM, $propertyWhitelist);

        foreach (self::FILTER_REQUEST_PARAMS as $filterParam) {
            $request->request->set($filterParam, \in_array($filterParam, $enabledFilters, true));
        }
    }
}
