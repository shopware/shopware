<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetailPrice\Factory\ProductDetailPriceBasicFactory;
use Shopware\ProductDetailPrice\Loader\ProductDetailPriceBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class ProductDetailPriceSearcher extends Searcher
{
    /**
     * @var ProductDetailPriceBasicFactory
     */
    private $factory;

    /**
     * @var ProductDetailPriceBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, ProductDetailPriceBasicFactory $factory, ProductDetailPriceBasicLoader $loader)
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

        $result = new ProductDetailPriceSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
