<?php

namespace Shopware\ProductMedia\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductMedia\Factory\ProductMediaBasicFactory;
use Shopware\ProductMedia\Loader\ProductMediaBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class ProductMediaSearcher extends Searcher
{
    /**
     * @var ProductMediaBasicFactory
     */
    private $factory;

    /**
     * @var ProductMediaBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, ProductMediaBasicFactory $factory, ProductMediaBasicLoader $loader)
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

        $result = new ProductMediaSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
