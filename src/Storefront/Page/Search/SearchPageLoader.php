<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\MatchQuery;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Search\Term\EntityScoreQueryBuilder;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\TranslationContext;
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

    /**
     * @var EntityScoreQueryBuilder
     */
    private $scoreQueryBuilder;

    public function __construct(
        ConfigServiceInterface $configService,
        StorefrontProductRepository $productRepository,
        KeywordSearchTermInterpreter $termInterpreter,
        EntityScoreQueryBuilder $scoreQueryBuilder
    ) {
        $this->configService = $configService;
        $this->productRepository = $productRepository;
        $this->termInterpreter = $termInterpreter;
        $this->scoreQueryBuilder = $scoreQueryBuilder;
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
        $criteria = $this->createCriteria(trim($searchTerm), $request, $context->getTranslationContext());

        $products = $this->productRepository->search($criteria, $context);

        $listingPageStruct = new SearchPageStruct();
        $listingPageStruct->setProducts($products);
        $listingPageStruct->setCriteria($criteria);
        $listingPageStruct->setShowListing(true);
        $listingPageStruct->setProductBoxLayout($config['searchProductBoxLayout']);

        return $listingPageStruct;
    }

    private function createCriteria(string $searchTerm, Request $request, TranslationContext $context): Criteria
    {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('page', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setFetchCount(true);
        $criteria->addFilter(new TermQuery('product.active', 1));

        $pattern = $this->termInterpreter->interpret($searchTerm, $context);

        $queries = $this->scoreQueryBuilder->buildScoreQueries($pattern, ProductDefinition::class, ProductDefinition::getEntityName());

        foreach ($queries as $query) {
            $criteria->addQuery($query);
        }

        return $criteria;
    }
}
