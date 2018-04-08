<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Config\ConfigServiceInterface;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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

    /**
     * @param string            $searchTerm
     * @param Request           $request
     * @param StorefrontContext $context
     *
     * @return SearchPageStruct
     */
    public function load(Request $request, StorefrontContext $context, bool $loadAggregations = true): SearchPageStruct
    {
        $config = $this->configService->getByShop($context->getShop()->getId(), null);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.active', 1));

        $this->eventDispatcher->dispatch(
            PageCriteriaCreatedEvent::NAME,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$loadAggregations) {
            $criteria->setAggregations([]);
        }

        $products = $this->productRepository->search($criteria, $context);

        $layout = $config['searchProductBoxLayout'] ?? 'basic';

        $page = new SearchPageStruct($context->getShop()->getCategoryId(), $products, $criteria);
        $page->setProductBoxLayout($layout);

        $this->eventDispatcher->dispatch(
            ListingPageLoadedEvent::NAME,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
