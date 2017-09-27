<?php declare(strict_types=1);

namespace Shopware\OrderState\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderState\Factory\OrderStateBasicFactory;
use Shopware\OrderState\Loader\OrderStateBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class OrderStateSearcher extends Searcher
{
    /**
     * @var OrderStateBasicFactory
     */
    private $factory;

    /**
     * @var OrderStateBasicLoader
     */
    private $loader;

    public function __construct(Connection $connection, SqlParser $parser, OrderStateBasicFactory $factory, OrderStateBasicLoader $loader)
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

        $result = new OrderStateSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());

        return $result;
    }
}
