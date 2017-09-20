<?php

namespace Shopware\OrderLineItem\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderLineItem\Factory\OrderLineItemBasicFactory;
use Shopware\OrderLineItem\Loader\OrderLineItemBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class OrderLineItemSearcher extends Searcher
{
    /**
     * @var OrderLineItemBasicFactory
     */
    private $factory;

    /**
     * @var OrderLineItemBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, OrderLineItemBasicFactory $factory, OrderLineItemBasicLoader $loader)
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

        $result = new OrderLineItemSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
