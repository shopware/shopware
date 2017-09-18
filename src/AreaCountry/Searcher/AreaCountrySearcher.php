<?php

namespace Shopware\AreaCountry\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryDetailFactory;
use Shopware\AreaCountry\Loader\AreaCountryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class AreaCountrySearcher extends Searcher
{
    /**
     * @var AreaCountryDetailFactory
     */
    private $factory;

    /**
     * @var AreaCountryBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, AreaCountryDetailFactory $factory, AreaCountryBasicLoader $loader)
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

        $result = new AreaCountrySearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
