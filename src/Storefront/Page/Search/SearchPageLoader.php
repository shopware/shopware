<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\ScoreQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Config\ConfigServiceInterface;
use Shopware\Storefront\Bridge\Product\Repository\StorefrontProductRepository;
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

    public function __construct(
        ConfigServiceInterface $configService,
        StorefrontProductRepository $productRepository,
        KeywordSearchTermInterpreter $termInterpreter
    ) {
        $this->configService = $configService;
        $this->productRepository = $productRepository;
        $this->termInterpreter = $termInterpreter;
    }

    /**
     * @param string            $searchTerm
     * @param Request           $request
     * @param StorefrontContext $context
     *
     * @return SearchPageStruct
     */
    public function load(string $searchTerm, Request $request, StorefrontContext $context): SearchPageStruct
    {
        $config = $this->configService->getByShop($context->getShop()->getId(), null);

        $criteria = $this->createCriteria(trim($searchTerm), $request, $context);

        $products = $this->productRepository->search($criteria, $context);

        $layout = $config['searchProductBoxLayout'] ?? 'basic';

        $listingPageStruct = new SearchPageStruct();
        $listingPageStruct->setProducts($products);
        $listingPageStruct->setCriteria($criteria);
        $listingPageStruct->setShowListing(true);
        $listingPageStruct->setProductBoxLayout($layout);

        return $listingPageStruct;
    }

    private function createCriteria(
        string $searchTerm,
        Request $request,
        StorefrontContext $context
    ): Criteria {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('page', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setFetchCount(Criteria::FETCH_COUNT_TOTAL);
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
            'product.searchKeywords.shopId',
            $context->getShop()->getId()
        ));

        return $criteria;
    }
}
