<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Config\CachedConfigService;
use Shopware\Search\Criteria;
use Shopware\Search\Query\MatchQuery;
use Shopware\Search\Query\TermQuery;
use Shopware\Storefront\Bridge\Product\Repository\StorefrontProductRepository;
use Symfony\Component\HttpFoundation\Request;

class SearchPageLoader
{
    /**
     * @var CachedConfigService $configService
     */
    private $configService;

    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * SearchPageLoader constructor.
     *
     * @param CachedConfigService         $configService
     * @param StorefrontProductRepository $productRepository
     */
    public function __construct(CachedConfigService $configService, StorefrontProductRepository $productRepository)
    {
        $this->configService = $configService;
        $this->productRepository = $productRepository;
    }

    public function load(
        string $searchTerm,
        Request $request,
        ShopContext $context
    ): SearchPageStruct {
        $config = $this->configService->getByShop($context->getShop()->getUuid(), $context->getShop()->getParentUuid());
        $criteria = $this->createCriteria($searchTerm, $request);
        $products = $this->productRepository->search($criteria, $context);

        $listingPageStruct = new SearchPageStruct();
        $listingPageStruct->setProducts($products);
        $listingPageStruct->setCriteria($criteria);
        $listingPageStruct->setShowListing(true);
        $listingPageStruct->setProductBoxLayout($config['searchProductBoxLayout']);

        return $listingPageStruct;
    }

    /**
     * @param string  $searchTerm
     * @param Request $request
     *
     * @return Criteria
     */
    private function createCriteria(string $searchTerm, Request $request): Criteria
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
        $criteria->addFilter(
            new MatchQuery('product.name', $searchTerm)
        );

        return $criteria;
    }
}
