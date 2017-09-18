<?php

namespace Shopware\AreaCountryState\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountryState\Factory\AreaCountryStateBasicFactory;
use Shopware\AreaCountryState\Loader\AreaCountryStateBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class AreaCountryStateSearcher extends Searcher
{
    /**
     * @var AreaCountryStateBasicFactory
     */
    private $factory;

    /**
     * @var AreaCountryStateBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, AreaCountryStateBasicFactory $factory, AreaCountryStateBasicLoader $loader)
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

        $result = new AreaCountryStateSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
