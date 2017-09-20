<?php

namespace Shopware\OrderDeliveryPosition\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderDeliveryPosition\Factory\OrderDeliveryPositionBasicFactory;
use Shopware\OrderDeliveryPosition\Loader\OrderDeliveryPositionBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class OrderDeliveryPositionSearcher extends Searcher
{
    /**
     * @var OrderDeliveryPositionBasicFactory
     */
    private $factory;

    /**
     * @var OrderDeliveryPositionBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, OrderDeliveryPositionBasicFactory $factory, OrderDeliveryPositionBasicLoader $loader)
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

        $result = new OrderDeliveryPositionSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
