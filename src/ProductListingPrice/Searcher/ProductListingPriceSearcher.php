<?php

namespace Shopware\ProductListingPrice\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductListingPrice\Factory\ProductListingPriceBasicFactory;
use Shopware\ProductListingPrice\Loader\ProductListingPriceBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class ProductListingPriceSearcher extends Searcher
{
    /**
     * @var ProductListingPriceBasicFactory
     */
    private $factory;

    /**
     * @var ProductListingPriceBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, ProductListingPriceBasicFactory $factory, ProductListingPriceBasicLoader $loader)
    {
        parent::__construct($connection, $parser);
        $this->factory = $factory;
        $this->loader = $loader;
    }

    protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        return $this->factory->createSearchQuery($criteria, $context);
    }

    protected function load(UuidSearchResult $uuidResult, TranslationContext $context): SearchResultInterface
    {
        $collection = $this->loader->load($uuidResult->getUuids(), $context);

        $result = new ProductListingPriceSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
