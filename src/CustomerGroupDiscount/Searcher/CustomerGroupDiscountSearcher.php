<?php

namespace Shopware\CustomerGroupDiscount\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Factory\CustomerGroupDiscountBasicFactory;
use Shopware\CustomerGroupDiscount\Loader\CustomerGroupDiscountBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class CustomerGroupDiscountSearcher extends Searcher
{
    /**
     * @var CustomerGroupDiscountBasicFactory
     */
    private $factory;

    /**
     * @var CustomerGroupDiscountBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, CustomerGroupDiscountBasicFactory $factory, CustomerGroupDiscountBasicLoader $loader)
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

        $result = new CustomerGroupDiscountSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
