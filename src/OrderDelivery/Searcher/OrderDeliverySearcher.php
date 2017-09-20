<?php

namespace Shopware\OrderDelivery\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderDelivery\Factory\OrderDeliveryDetailFactory;
use Shopware\OrderDelivery\Loader\OrderDeliveryBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class OrderDeliverySearcher extends Searcher
{
    /**
     * @var OrderDeliveryDetailFactory
     */
    private $factory;

    /**
     * @var OrderDeliveryBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, OrderDeliveryDetailFactory $factory, OrderDeliveryBasicLoader $loader)
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

        $result = new OrderDeliverySearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
