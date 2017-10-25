<?php declare(strict_types=1);

namespace Shopware\Holiday\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Holiday\Factory\HolidayBasicFactory;
use Shopware\Holiday\Reader\HolidayBasicReader;
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
     * @var HolidayBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, HolidayBasicFactory $factory, HolidayBasicReader $reader)
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

        $result = new HolidaySearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
