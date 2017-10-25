<?php declare(strict_types=1);

namespace Shopware\ProductStream\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductStream\Factory\ProductStreamBasicFactory;
use Shopware\ProductStream\Reader\ProductStreamBasicReader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class ProductStreamSearcher extends Searcher
{
    /**
     * @var ProductStreamBasicFactory
     */
    private $factory;

    /**
     * @var ProductStreamBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, ProductStreamBasicFactory $factory, ProductStreamBasicReader $reader)
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

        $result = new ProductStreamSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
