<?php declare(strict_types=1);

namespace Shopware\Album\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Album\Factory\AlbumDetailFactory;
use Shopware\Album\Reader\AlbumBasicReader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class AlbumSearcher extends Searcher
{
    /**
     * @var AlbumDetailFactory
     */
    private $factory;

    /**
     * @var AlbumBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, AlbumDetailFactory $factory, AlbumBasicReader $reader)
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

        $result = new AlbumSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
