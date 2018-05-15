<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Config\ConfigServiceInterface;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
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

    public function load(SearchPageRequest $request, StorefrontContext $context): SearchPageStruct
    {
        $config = $this->configService->getByShop($context->getApplication()->getId(), null);

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
