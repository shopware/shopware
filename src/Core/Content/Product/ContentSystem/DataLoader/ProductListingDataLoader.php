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
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\StructuredPropertyType;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<ProductListingResult>
 */
#[Package('framework')]
class ProductListingDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_listing';

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

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof ProductListingLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->property ?? 'navigationId';
        $navigationId = $element->getProperty($propertyName);

        if (!\is_string($navigationId)) {
            return ContentDataLoaderResult::notFound();
        }

        $navigationId = u($navigationId)->lower()->toString();

        if (!Uuid::isValid($navigationId)) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($element, $config);

        $response = $this->listingRoute->load(
            $navigationId,
            $this->prepareRequest($element, $request, $context),
            $context,
            $criteria
        );
        $result = $response->getResult();

        // ProductListingRoute internally adds cache tags via CacheTagCollector
        return ContentDataLoaderResult::cachedExternally($result);
    }

    /**
     * Translates the element's listing configuration into the request parameters the listing route reads, the
     * same way the CMS product-listing element does: a disabled filter toggle switches its handler off, so the
     * filter contributes neither an aggregation nor a post-filter, and `defaultSorting` preselects an order.
     *
     * The two kinds of parameter reach the route differently, because their scope differs.
     *
     * Filter parameters describe which filters this element offers, so they are set on a duplicate: the
     * hydrator threads one Request instance through every element's loader in document order, and one panel's
     * filter configuration must not narrow another element's listing.
     *
     * `order` describes the products themselves, so it is set on the shared request, the way the CMS element
     * does it: the element that renders the sorting select is not the element that renders the products, and
     * every listing query on the page has to open in the configured order. Only elements hydrated after this
     * one can see it — a layout therefore has to place its sorting select ahead of the listings it sorts.
     */
    private function prepareRequest(ContentElement $element, Request $request, SalesChannelContext $context): Request
    {
        $parameters = [];

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
     * Stored comma-separated, the way the CMS element stores its `filters` list: the element type system has no
     * list type, and its `object` type requires declared nested keys ({@see StructuredPropertyType}), which an
     * arbitrary id set does not have.
     *
     * Narrowing the groups is cheaper than the default, not more expensive: the handler wraps both terms
     * aggregations in a groupId FilterAggregation, so fewer buckets come back and fewer option and group
     * entities are loaded afterwards.
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
     * none, the id resolves to nothing, or the request already carries an order — a visitor's own choice is never
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
     */
    private function buildCriteria(ContentElement $element, ProductListingLoaderConfig $config): Criteria
    {
        $criteria = new Criteria();

        foreach ($config->associations as $association) {
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
