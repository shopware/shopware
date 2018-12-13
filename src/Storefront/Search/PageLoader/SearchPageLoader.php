<?php declare(strict_types=1);

namespace Shopware\Storefront\Search\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Listing\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Listing\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Search\Page\SearchPageRequest;
use Shopware\Storefront\Search\Page\SearchPageStruct;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchPageLoader
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

    public function load(SearchPageRequest $request, CheckoutContext $context): SearchPageStruct
    {
        $config = [];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', 1));

        $this->eventDispatcher->dispatch(
            PageCriteriaCreatedEvent::NAME,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->resetAggregations();
        }

        $products = $this->productRepository->search($criteria, $context);

        $layout = $config['searchProductBoxLayout'] ?? 'basic';

        $page = new SearchPageStruct(null, $products, $criteria);
        $page->setProductBoxLayout($layout);

        $this->eventDispatcher->dispatch(
            ListingPageLoadedEvent::NAME,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
