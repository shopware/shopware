<?php

namespace Shopware\Currency\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Struct\CurrencyHydrator;
use Shopware\Currency\Struct\CurrencySearchResult;
use Shopware\Shop\Struct\ShopSearchResult;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Search\Criteria;
use Shopware\Search\Search;
use Shopware\Search\SearchResultInterface;

class CurrencySearcher extends Search
{
    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var CurrencyHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, array $handlers, FieldHelper $fieldHelper, CurrencyHydrator $hydrator)
    {
        parent::__construct($connection, $handlers);
        $this->fieldHelper = $fieldHelper;
        $this->hydrator = $hydrator;
    }

    protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->fieldHelper->getCurrencyFields());
        $query->from('s_core_currencies', 'currency');

        return $query;
    }

    protected function createResult(array $rows, int $total): SearchResultInterface
    {
        $structs = array_map(function(array $row) {
            return $this->hydrator->hydrate($row);
        }, $rows);

        return new CurrencySearchResult($structs, $total);
    }
}