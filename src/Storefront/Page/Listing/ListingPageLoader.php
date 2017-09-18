<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Category\Repository\CategoryRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Repository\StorefrontProductRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermQuery;
use Symfony\Component\HttpFoundation\Request;

class ListingPageLoader
{
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    public function __construct(
        CategoryRepository $categoryRepository,
        StorefrontProductRepository $productRepository
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
    }

    public function load(
        string $categoryUuid,
        Request $request,
        ShopContext $context
    ): ListingPageStruct
    {
        $criteria = $this->createCriteria($categoryUuid, $request);

        $products = $this->productRepository->search($criteria, $context);

        $category = $this->categoryRepository->readDetail(
            [$categoryUuid],
            $context->getTranslationContext()
        )->get($categoryUuid);

        $listingPageStruct = new ListingPageStruct();
        $listingPageStruct->setCategory($category);
        $listingPageStruct->setProducts($products);
        $listingPageStruct->setCriteria($criteria);
        $listingPageStruct->setShowListing(true);

        return $listingPageStruct;
    }

    /**
     * @param string $categoryUuid
     * @param Request $request
     * @return Criteria
     */
    public function createCriteria(string $categoryUuid, Request $request): Criteria
    {
        $limit = 20;
        if ($request->get('limit')) {
            $limit = (int)$request->get('limit');
        }
        $page = 1;
        if ($request->get('page')) {
            $page = (int)$request->get('page');
        }

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(new TermQuery('product.categories.uuid', $categoryUuid));

        return $criteria;
    }
}