<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Psr\Log\LoggerInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Listing\ListingHandler\ListingHandlerRegistry;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
use Symfony\Component\HttpFoundation\Request;

class ListingPageLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var ListingHandlerRegistry
     */
    private $handlerRegistry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        StorefrontProductRepository $productRepository,
        ListingHandlerRegistry $listingHandlerRegistry,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->handlerRegistry = $listingHandlerRegistry;
        $this->logger = $logger;
    }

    public function load(string $categoryId, Request $request, StorefrontContext $context, bool $loadAggregations = true): ListingPageStruct
    {
        $criteria = $this->createCriteria($categoryId, $request, $context);

        if (!$loadAggregations) {
            $criteria->setAggregations([]);
        }
        $products = $this->productRepository->search($criteria, $context);

        $currentPage = $request->query->getInt('p', 1);

        $parameters = $request->query->all();
        $parameters['id'] = $categoryId;

        $listingPageStruct = new ListingPageStruct($products, $criteria, $parameters);
        $listingPageStruct->setCurrentPage($currentPage);
        $listingPageStruct->setPageCount($this->getPageCount($products, $criteria, $currentPage));
        $listingPageStruct->setShowListing(true);
        $listingPageStruct->setCurrentSorting($request->query->get('o'));
        $listingPageStruct->setProductBoxLayout('basic');

        $this->logger->info('Listing search result', json_decode(json_encode($products), true));

        $this->handlerRegistry->preparePage($listingPageStruct, $products, $context);

        return $listingPageStruct;
    }

    private function createCriteria(string $categoryId, Request $request, StorefrontContext $context): Criteria
    {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('p', 1);

        $criteria = new Criteria();

        //pagination
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);

        //base filtering of category listings
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(new TermQuery('product.categoriesRo.id', $categoryId));

        //aggregations
        $this->handlerRegistry->prepareCriteria($request, $criteria, $context);

        return $criteria;
    }

    private function getPageCount(ProductSearchResult $products, Criteria  $criteria, int $currentPage): int
    {
        $pageCount = (int) round($products->getTotal() / $criteria->getLimit());
        $pageCount = max(1, $pageCount);
        if ($pageCount > 1 && $criteria->fetchCount() === Criteria::FETCH_COUNT_NEXT_PAGES) {
            $pageCount += $currentPage;
        }

        return $pageCount;
    }
}
