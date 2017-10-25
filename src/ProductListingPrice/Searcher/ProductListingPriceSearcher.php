<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductListingPrice\Factory\ProductListingPriceBasicFactory;
use Shopware\ProductListingPrice\Reader\ProductListingPriceBasicReader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class ProductListingPriceSearcher extends Searcher
{
    /**
     * @var ProductListingPriceBasicFactory
     */
    private $factory;

    /**
     * @var ProductListingPriceBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, ProductListingPriceBasicFactory $factory, ProductListingPriceBasicReader $reader)
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

        $result = new ProductListingPriceSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
