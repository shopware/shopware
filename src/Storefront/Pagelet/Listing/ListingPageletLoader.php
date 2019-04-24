<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ListingPageletLoader implements PageLoaderInterface
{
    public const PRODUCT_VISIBILITY = 'product-min-visibility';

    /**
     * @var SalesChannelRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        SalesChannelRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): StorefrontSearchResult
    {
        $criteria = new Criteria();

        if ($visibility = $request->get(self::PRODUCT_VISIBILITY)) {
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new RangeFilter('product.visibilities.visibility', [RangeFilter::GTE => (int) $visibility]),
                        new EqualsFilter('product.visibilities.salesChannelId', $context->getSalesChannel()->getId()),
                    ]
                )
            );
        }

        $this->eventDispatcher->dispatch(
            ListingPageletCriteriaCreatedEvent::NAME,
            new ListingPageletCriteriaCreatedEvent($criteria, $context, $request)
        );

        if ($request->get('no-aggregations')) {
            $criteria->resetAggregations();
        }

        $products = $this->productRepository->search($criteria, $context);

        $result = StorefrontSearchResult::createFrom($products);

        $this->eventDispatcher->dispatch(
            ListingPageletLoadedEvent::NAME,
            new ListingPageletLoadedEvent($result, $context, $request)
        );

        return $result;
    }
}
