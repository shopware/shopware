<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Parser\SqlParser;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\Searcher;
use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductListingPrice\Factory\ProductListingPriceBasicFactory;
use Shopware\ProductListingPrice\Reader\ProductListingPriceBasicReader;

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
