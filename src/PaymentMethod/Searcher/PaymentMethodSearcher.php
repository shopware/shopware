<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Searcher;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\PaymentMethod\Factory\PaymentMethodDetailFactory;
use Shopware\PaymentMethod\Reader\PaymentMethodBasicReader;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\SqlParser;
use Shopware\Search\QueryBuilder;
use Shopware\Search\Searcher;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

class PaymentMethodSearcher extends Searcher
{
    /**
     * @var PaymentMethodDetailFactory
     */
    private $factory;

    /**
     * @var PaymentMethodBasicReader
     */
    private $reader;

    public function __construct(Connection $connection, SqlParser $parser, PaymentMethodDetailFactory $factory, PaymentMethodBasicReader $reader)
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

        $result = new PaymentMethodSearchResult($collection->getElements());

        $result->setTotal($uuidResult->getTotal());
        $result->setCriteria($criteria);
        $result->setContext($context);

        return $result;
    }
}
