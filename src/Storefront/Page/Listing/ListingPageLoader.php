<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
use Symfony\Component\HttpFoundation\Request;

class ListingPageLoader
{
    /**
     * @var \Shopware\StorefrontApi\Product\StorefrontProductRepository
     */
    private $productRepository;

    public function __construct(StorefrontProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function load(string $categoryId, Request $request, StorefrontContext $context): ListingPageStruct
    {
        $criteria = $this->createCriteria($categoryId, $request, $context);
        $products = $this->productRepository->search($criteria, $context);

        $currentPage = $request->query->getInt('p', 1);

        $listingPageStruct = new ListingPageStruct();
        $listingPageStruct->setProducts($products);
        $listingPageStruct->setCriteria($criteria);
        $listingPageStruct->setShowListing(true);

        $listingPageStruct->setCurrentPage($currentPage);
        $listingPageStruct->setPageCount(
            $this->getPageCount($products, $criteria, $currentPage)
        );

        return $listingPageStruct;
    }

    private function createCriteria(
        string $categoryId,
        Request $request,
        StorefrontContext $context
    ): Criteria {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('p', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(new TermQuery('product.categoryTree', $categoryId));
        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);

        return $criteria;
    }

    /**
     * @param $products
     * @param $criteria
     * @param $currentPage
     *
     * @return int|mixed
     */
    private function getPageCount(ProductSearchResult $products, Criteria  $criteria, int $currentPage)
    {
        $pageCount = (int) round($products->getTotal() / $criteria->getLimit());
        $pageCount = max(1, $pageCount);
        if ($pageCount > 1 && $criteria->fetchCount() === Criteria::FETCH_COUNT_NEXT_PAGES) {
            $pageCount += $currentPage;
        }

        return $pageCount;
    }
}
