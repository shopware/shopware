<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Context\Struct\ShopContext;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermQuery;
use Shopware\Storefront\Bridge\Product\Repository\StorefrontProductRepository;
use Symfony\Component\HttpFoundation\Request;

class ListingPageLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    public function __construct(
        StorefrontProductRepository $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    public function load(
        string $categoryUuid,
        Request $request,
        ShopContext $context
    ): ListingPageStruct {
        $criteria = $this->createCriteria($categoryUuid, $request, $context);

        $products = $this->productRepository->search($criteria, $context);

        $listingPageStruct = new ListingPageStruct();
        $listingPageStruct->setProducts($products);
        $listingPageStruct->setCriteria($criteria);
        $listingPageStruct->setShowListing(true);

        return $listingPageStruct;
    }

    /**
     * @param string  $categoryUuid
     * @param Request $request
     *
     * @return Criteria
     */
    private function createCriteria(
        string $categoryUuid,
        Request $request,
        ShopContext $context
    ): Criteria {
        $limit = 20;
        if ($request->get('limit')) {
            $limit = (int) $request->get('limit');
        }
        $page = 1;
        if ($request->get('page')) {
            $page = (int) $request->get('page');
        }

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(new TermQuery('product.categoryTree.uuid', $categoryUuid));
        $criteria->setFetchCount(true);

        return $criteria;
    }
}
