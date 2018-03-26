<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\ScoreQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Config\ConfigServiceInterface;
use Shopware\Storefront\Page\Listing\ListingHandler\ListingHandlerRegistry;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
use Shopware\StorefrontApi\Search\KeywordSearchTermInterpreter;
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
     * @var KeywordSearchTermInterpreter
     */
    private $termInterpreter;

    /**
     * @var ListingHandlerRegistry
     */
    private $listingHandlerRegistry;

    public function __construct(
        ConfigServiceInterface $configService,
        StorefrontProductRepository $productRepository,
        ListingHandlerRegistry $listingHandlerRegistry,
        KeywordSearchTermInterpreter $termInterpreter
    ) {
        $this->configService = $configService;
        $this->productRepository = $productRepository;
        $this->termInterpreter = $termInterpreter;
        $this->listingHandlerRegistry = $listingHandlerRegistry;
    }

    /**
     * @param string            $searchTerm
     * @param Request           $request
     * @param StorefrontContext $context
     *
     * @return SearchPageStruct
     */
    public function load(string $searchTerm, Request $request, StorefrontContext $context, bool $loadAggregations = true): SearchPageStruct
    {
        $config = $this->configService->getByShop($context->getShop()->getId(), null);

        $criteria = $this->createCriteria(trim($searchTerm), $request, $context);
        if (!$loadAggregations) {
            $criteria->setAggregations([]);
        }

        $products = $this->productRepository->search($criteria, $context);

        $layout = $config['searchProductBoxLayout'] ?? 'basic';

        $currentPage = $request->query->getInt('p', 1);

        $page = new SearchPageStruct($products, $criteria);
        $page->setCurrentPage($currentPage);
        $page->setPageCount($this->getPageCount($products, $criteria, $currentPage));
        $page->setShowListing(true);
        $page->setCurrentSorting($request->query->get('o'));
        $page->setProductBoxLayout($layout);

        $this->listingHandlerRegistry->preparePage($page, $products, $context);

        return $page;
    }

    private function createCriteria(string $searchTerm, Request $request, StorefrontContext $context): Criteria
    {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('p', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);
        $criteria->addFilter(new TermQuery('product.active', 1));

        $pattern = $this->termInterpreter->interpret($searchTerm, $context->getShopContext());
        $keywords = $queries = [];
        foreach ($pattern->getTerms() as $term) {
            $queries[] = new ScoreQuery(
                new TermQuery('product.searchKeywords.keyword', $term->getTerm()),
                $term->getScore(),
                'product.searchKeywords.ranking'
            );
            $keywords[] = $term->getTerm();
        }

        foreach ($queries as $query) {
            $criteria->addQuery($query);
        }

        $criteria->addFilter(new TermsQuery(
            'product.searchKeywords.keyword',
            array_values($keywords)
        ));

        $criteria->addFilter(new TermQuery(
            'product.searchKeywords.languageId',
            Defaults::LANGUAGE
        ));

        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);

        //aggregations
        $this->listingHandlerRegistry->prepareCriteria($request, $criteria, $context);

        return $criteria;
    }

    private function getPageCount(ProductSearchResult $products, Criteria $criteria, int $currentPage)
    {
        $pageCount = (int) round($products->getTotal() / $criteria->getLimit());
        $pageCount = max(1, $pageCount);
        if ($pageCount > 1 && $criteria->fetchCount() === Criteria::FETCH_COUNT_NEXT_PAGES) {
            $pageCount += $currentPage;
        }

        return $pageCount;
    }
}
