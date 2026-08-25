<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves the aggregations of a product listing to an element that renders filters but no products, such as a
 * filter panel placed beside a listing.
 *
 * The narrower produced type is the point: `only-aggregations` leaves the route's result without products,
 * total count or sorting, so handing back the whole {@see \Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult}
 * would offer a consumer fields that silently read as empty or zero.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<AggregationResultCollection>
 */
#[Package('framework')]
class ProductListingAggregationsDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_listing_aggregations';

    public function __construct(private readonly ProductListingElementLoader $listingLoader)
    {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof ProductListingAggregationsLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $result = $this->listingLoader->load(
            $element,
            $context,
            $request,
            $config->property,
            parameters: ['only-aggregations' => true]
        );

        if ($result === null) {
            return ContentDataLoaderResult::notFound();
        }

        // ProductListingRoute internally adds cache tags via CacheTagCollector
        return ContentDataLoaderResult::cachedExternally($result->getAggregations());
    }
}
