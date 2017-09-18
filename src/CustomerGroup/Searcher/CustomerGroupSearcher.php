<?php

namespace Shopware\CustomerGroup\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Factory\CustomerGroupDetailFactory;
use Shopware\CustomerGroup\Loader\CustomerGroupBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class CustomerGroupSearcher extends Searcher
{
    /**
     * @var CustomerGroupDetailFactory
     */
    private $factory;

    /**
     * @var CustomerGroupBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, CustomerGroupDetailFactory $factory, CustomerGroupBasicLoader $loader)
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

        $result = new CustomerGroupSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
