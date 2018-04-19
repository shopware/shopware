<?php

namespace Shopware\Shop\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Struct\ShopSearchResult;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Search\Criteria;
use Shopware\Search\Search;
use Shopware\Search\SearchResultInterface;
use Shopware\Shop\Gateway\Query\ShopIdentityQuery;
use Shopware\Shop\Struct\ShopHydrator;

class ShopSearcher extends Search
{
    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var ShopHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, array $handlers, FieldHelper $fieldHelper, ShopHydrator $hydrator)
    {
        parent::__construct($connection, $handlers);
        $this->fieldHelper = $fieldHelper;
        $this->hydrator = $hydrator;
    }

    protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        return new ShopIdentityQuery($this->connection, $this->fieldHelper, $context);
    }

    protected function createResult(array $rows, int $total): SearchResultInterface
    {
        $structs = array_map(function(array $row) {
            return $this->hydrator->hydrateIdentity($row);
        }, $rows);

        return new ShopSearchResult($structs, $total);
    }
}