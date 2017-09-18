<?php

namespace Shopware\TaxAreaRule\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;
use Shopware\TaxAreaRule\Factory\TaxAreaRuleBasicFactory;
use Shopware\TaxAreaRule\Loader\TaxAreaRuleBasicLoader;

class TaxAreaRuleSearcher extends Searcher
{
    /**
     * @var TaxAreaRuleBasicFactory
     */
    private $factory;

    /**
     * @var TaxAreaRuleBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, TaxAreaRuleBasicFactory $factory, TaxAreaRuleBasicLoader $loader)
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

        $result = new TaxAreaRuleSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
