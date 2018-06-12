<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\System\Config\Util\ConfigServiceInterface;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchPageLoader
{
    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        ConfigServiceInterface $configService,
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->configService = $configService;
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(SearchPageRequest $request, CheckoutContext $context): SearchPageStruct
    {
        $config = $this->configService->get($context->getTouchpoint()->getId(), null);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.active', 1));

        $this->eventDispatcher->dispatch(
            PageCriteriaCreatedEvent::NAME,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->setAggregations([]);
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
