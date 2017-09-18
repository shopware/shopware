<?php

namespace Shopware\Shop\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;
use Shopware\Shop\Factory\ShopDetailFactory;
use Shopware\Shop\Loader\ShopBasicLoader;

class ShopSearcher extends Searcher
{
    /**
     * @var ShopDetailFactory
     */
    private $factory;

    /**
     * @var ShopBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, ShopDetailFactory $factory, ShopBasicLoader $loader)
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

        $result = new ShopSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
