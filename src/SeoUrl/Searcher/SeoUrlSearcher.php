<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Parser\SqlParser;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\Searcher;
use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\SeoUrl\Factory\SeoUrlBasicFactory;
use Shopware\SeoUrl\Reader\SeoUrlBasicReader;

class SeoUrlSearcher extends Searcher
{
    /**
     * @var SeoUrlBasicFactory
     */
    private $factory;

    /**
     * @var SeoUrlBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, SeoUrlBasicFactory $factory, SeoUrlBasicReader $reader)
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

        $result = new SeoUrlSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
