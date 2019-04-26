<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ListingPageletLoader implements PageLoaderInterface
{
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
