<?php

namespace Shopware\Area\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Area\Factory\AreaDetailFactory;
use Shopware\Area\Loader\AreaBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class AreaSearcher extends Searcher
{
    /**
     * @var AreaDetailFactory
     */
    private $factory;

    /**
     * @var AreaBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, AreaDetailFactory $factory, AreaBasicLoader $loader)
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

        $result = new AreaSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
