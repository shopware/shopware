<?php declare(strict_types=1);

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
use Shopware\TaxAreaRule\Reader\TaxAreaRuleBasicReader;

class TaxAreaRuleSearcher extends Searcher
{
    /**
     * @var TaxAreaRuleBasicFactory
     */
    private $factory;

    /**
     * @var TaxAreaRuleBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, TaxAreaRuleBasicFactory $factory, TaxAreaRuleBasicReader $reader)
    {
        parent::__construct($connection, $parser);
        $this->factory = $factory;
        $this->reader = $reader;
    }

    protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        return $this->factory->createSearchQuery($criteria, $context);
    }

    protected function load(UuidSearchResult $uuidResult, Criteria $criteria, TranslationContext $context): SearchResultInterface
    {
        $collection = $this->reader->readBasic($uuidResult->getUuids(), $context);

        $result = new TaxAreaRuleSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
