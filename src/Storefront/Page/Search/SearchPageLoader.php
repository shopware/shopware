<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Config\CachedConfigService;
use Shopware\Search\Criteria;
use Shopware\Search\Query\MatchQuery;
use Shopware\Search\Query\NestedQuery;
use Shopware\Search\Query\TermQuery;
use Shopware\Storefront\Bridge\Product\Repository\StorefrontProductRepository;
use Symfony\Component\HttpFoundation\Request;

class SearchPageLoader
{
    /**
     * @var CachedConfigService
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

    /**
     * @param string      $searchTerm
     * @param Request     $request
     * @param ShopContext $context
     *
     * @return SearchPageStruct
     */
    public function load(string $searchTerm, Request $request, ShopContext $context): SearchPageStruct
    {
        $config = $this->configService->getByShop($context->getShop()->getUuid(), $context->getShop()->getParentUuid());
        $criteria = $this->createCriteria(trim($searchTerm), $request, $config['enableAndSearchLogic']);
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
     * @param bool    $isAndSearchLogicEnabled
     *
     * @return Criteria
     */
    private function createCriteria(string $searchTerm, Request $request, bool $isAndSearchLogicEnabled): Criteria
    {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('page', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(
            $this->createSearchTermFilter($searchTerm, $isAndSearchLogicEnabled)
        );

        return $criteria;
    }

    /**
     * @param string $searchTerm
     * @param bool   $isAndSearchLogicEnabled
     *
     * @return NestedQuery
     */
    private function createSearchTermFilter(string $searchTerm, bool $isAndSearchLogicEnabled): NestedQuery
    {
        $nameQueries = [];
        $descriptionQueries = [];
        $queryOperator = $isAndSearchLogicEnabled ? 'AND' : 'OR';
        $searchTerms = explode(' ', $searchTerm);

        foreach ($searchTerms as $term) {
            $nameQueries[] = new MatchQuery('product.name', trim($term));
            $descriptionQueries[] = new MatchQuery('product.description', trim($term));
        }

        return new NestedQuery(
            [
                new NestedQuery($nameQueries, $queryOperator),
                new NestedQuery($descriptionQueries, $queryOperator),
            ],
            'OR'
        );
    }
}
