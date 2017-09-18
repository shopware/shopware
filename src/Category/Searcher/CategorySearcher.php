<?php

namespace Shopware\Category\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Category\Factory\CategoryDetailFactory;
use Shopware\Category\Loader\CategoryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class CategorySearcher extends Searcher
{
    /**
     * @var CategoryDetailFactory
     */
    private $factory;

    /**
     * @var CategoryBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, CategoryDetailFactory $factory, CategoryBasicLoader $loader)
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

        $result = new CategorySearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
