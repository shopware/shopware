<?php

namespace Shopware\Holiday\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Holiday\Factory\HolidayBasicFactory;
use Shopware\Holiday\Loader\HolidayBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class HolidaySearcher extends Searcher
{
    /**
     * @var HolidayBasicFactory
     */
    private $factory;

    /**
     * @var HolidayBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, HolidayBasicFactory $factory, HolidayBasicLoader $loader)
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

        $result = new HolidaySearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
