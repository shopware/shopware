<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageletLoader implements PageLoaderInterface
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): StorefrontSearchResult
    {
        $criteria = new Criteria();

        $this->eventDispatcher->dispatch(
            ListingPageletCriteriaCreatedEvent::NAME,
            new ListingPageletCriteriaCreatedEvent($criteria, $context, $request)
        );

        if ($request->getParam('no-aggregations')) {
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
