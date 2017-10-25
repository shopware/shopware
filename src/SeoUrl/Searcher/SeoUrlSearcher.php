<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;
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
