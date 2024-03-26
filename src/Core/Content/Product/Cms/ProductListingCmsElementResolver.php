<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
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

        // if we have no specific order given at this point, set the order to be the highest's priority available sorting
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

            $criteria = new Criteria([array_key_first($availableSortings)]);

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
        // set up the default behavior
        $defaults = ['manufacturer-filter', 'rating-filter', 'shipping-free-filter', 'price-filter', 'property-filter'];

        $request->request->set('property-whitelist', null);

        $config = $slot->get('config');

        if (isset($config['propertyWhitelist']['value']) && (is_countable($config['propertyWhitelist']['value']) ? \count($config['propertyWhitelist']['value']) : 0) > 0) {
            $request->request->set('property-whitelist', $config['propertyWhitelist']['value']);
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
