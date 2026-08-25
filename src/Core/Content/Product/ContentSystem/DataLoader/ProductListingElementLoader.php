<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PriceListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\RatingListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\StructuredPropertyType;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * Runs the listing route for a content element, translating the element's listing configuration into the
 * request parameters the route reads. Shared by every loader that serves a slice of a listing result, so the
 * filter, whitelist and sorting rules are defined once.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ProductListingElementLoader
{
    /**
     * Element property holding the toggle, mapped to the request parameter the matching filter handler reads.
     */
    private const FILTER_TOGGLE_PARAMETERS = [
        'showManufacturerFilter' => ManufacturerListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        'showRatingFilter' => RatingListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        'showPriceFilter' => PriceListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        'showShippingFreeFilter' => ShippingFreeListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        'showPropertyFilter' => PropertyListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
    ];

    /**
     * @param EntityRepository<ProductSortingCollection> $sortingRepository
     */
    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute,
        private readonly EntityRepository $sortingRepository
    ) {
    }

    /**
     * Null when the element carries no usable navigation id, so a caller can report notFound rather than throw.
     *
     * @param list<string> $associations
     * @param array<string, bool|list<string>> $parameters extra request parameters, for a caller narrowing the query
     */
    public function load(
        ContentElement $element,
        SalesChannelContext $context,
        Request $request,
        ?string $propertyName = null,
        array $associations = [],
        array $parameters = []
    ): ?ProductListingResult {
        $navigationId = $element->getProperty($propertyName ?? 'navigationId');

        if (!\is_string($navigationId)) {
            return null;
        }

        $navigationId = u($navigationId)->lower()->toString();

        if (!Uuid::isValid($navigationId)) {
            return null;
        }

        return $this->listingRoute->load(
            $navigationId,
            $this->prepareRequest($element, $request, $context, $parameters),
            $context,
            $this->buildCriteria($element, $associations)
        )->getResult();
    }

    /**
     * Translates the element's listing configuration into request parameters: a disabled filter toggle switches
     * its handler off, and `defaultSorting` preselects an order.
     *
     * The two kinds differ in scope. Filter parameters describe this element only, so they go on a duplicate:
     * the hydrator threads one Request through every loader in document order, and one panel must not narrow
     * another element's listing. `order` describes the products, so it goes on the shared request, because the
     * element rendering the sorting select is not the one rendering the products. Only elements hydrated after
     * this one see it, so a layout has to place its sorting select ahead of the listings it sorts.
     *
     * @param array<string, bool|list<string>> $parameters
     */
    private function prepareRequest(
        ContentElement $element,
        Request $request,
        SalesChannelContext $context,
        array $parameters
    ): Request {
        foreach (self::FILTER_TOGGLE_PARAMETERS as $property => $parameter) {
            if ($element->getProperty($property) === false) {
                $parameters[$parameter] = false;
            }
        }

        // The handler honours its enabled flag only while no whitelist is set, so a whitelist is withheld when
        // the toggle is off. Off then means no property filters at all, rather than the CMS element's
        // mutually exclusive "all groups or exactly these".
        $propertyWhitelist = $element->getProperty('showPropertyFilter') === false
            ? []
            : $this->resolvePropertyWhitelist($element);

        if ($propertyWhitelist !== []) {
            $parameters[PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM] = $propertyWhitelist;
        }

        $order = $this->resolveDefaultSortingKey($element, $request, $context);

        if ($order !== null) {
            $request->request->set('order', $order);
        }

        $request = $request->duplicate();

        foreach ($parameters as $parameter => $value) {
            $request->request->set($parameter, $value);
        }

        return $request;
    }

    /**
     * The property_group ids the element restricts its property filters to, empty when it restricts nothing.
     * Stored comma-separated because the element type system has no list type, and its `object` type requires
     * declared nested keys ({@see StructuredPropertyType}), which an arbitrary id set does not have.
     *
     * Narrowing is cheaper than the default: the handler wraps the terms aggregations in a groupId
     * FilterAggregation, so fewer buckets and fewer option entities come back.
     *
     * @return list<string>
     */
    private function resolvePropertyWhitelist(ContentElement $element): array
    {
        $whitelist = $element->getProperty('propertyWhitelist');

        if (!\is_string($whitelist) || $whitelist === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $whitelist))));
    }

    /**
     * The sorting key of the element's `defaultSorting` (a product_sorting id), or null when the element declares
     * none, the id resolves to nothing, or the request already carries an order. A visitor's own choice is never
     * overwritten.
     */
    private function resolveDefaultSortingKey(ContentElement $element, Request $request, SalesChannelContext $context): ?string
    {
        $defaultSorting = $element->getProperty('defaultSorting');

        if (!\is_string($defaultSorting) || $defaultSorting === '') {
            return null;
        }

        // Only the query string carries a visitor's own choice. The request bag cannot be trusted here: the
        // Storefront loads the classic navigation page before the content layout, and that page's listing run
        // leaves the channel's default `order` in the bag, which would otherwise look like a visitor choice.
        if ($request->query->has('order')) {
            return null;
        }

        $criteria = new Criteria([$defaultSorting]);
        $criteria->setTitle('content-system::product-listing-default-sorting');

        return $this->sortingRepository->search($criteria, $context->getContext())->getEntities()->first()?->getKey();
    }

    /**
     * Element properties can override requirement config associations.
     *
     * @param list<string> $associations
     */
    private function buildCriteria(ContentElement $element, array $associations): Criteria
    {
        $criteria = new Criteria();

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        $elementAssociations = $element->getProperty('associations');
        if (\is_array($elementAssociations)) {
            foreach ($elementAssociations as $association) {
                if (\is_string($association)) {
                    $criteria->addAssociation($association);
                }
            }
        }

        return $criteria;
    }
}
