<?php declare(strict_types=1);

namespace Shopware\Tax\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;
use Shopware\Tax\Factory\TaxBasicFactory;
use Shopware\Tax\Loader\TaxBasicLoader;

class TaxSearcher extends Searcher
{
    /**
     * @var TaxBasicFactory
     */
    private $factory;

    /**
     * @var TaxBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, TaxBasicFactory $factory, TaxBasicLoader $loader)
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

        $result = new TaxSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
