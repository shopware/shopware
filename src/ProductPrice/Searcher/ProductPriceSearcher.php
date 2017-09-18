<?php

namespace Shopware\ProductPrice\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductPrice\Factory\ProductPriceBasicFactory;
use Shopware\ProductPrice\Loader\ProductPriceBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class ProductPriceSearcher extends Searcher
{
    /**
     * @var ProductPriceBasicFactory
     */
    private $factory;

    /**
     * @var ProductPriceBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, ProductPriceBasicFactory $factory, ProductPriceBasicLoader $loader)
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

        $result = new ProductPriceSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
