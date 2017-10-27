<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Parser\SqlParser;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\Searcher;
use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\AreaCountry\Factory\AreaCountryDetailFactory;
use Shopware\AreaCountry\Reader\AreaCountryBasicReader;
use Shopware\Context\Struct\TranslationContext;

class AreaCountrySearcher extends Searcher
{
    /**
     * @var AreaCountryDetailFactory
     */
    private $factory;

    /**
     * @var AreaCountryBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, AreaCountryDetailFactory $factory, AreaCountryBasicReader $reader)
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

        $result = new AreaCountrySearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
