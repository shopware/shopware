<?php

namespace Shopware\Search;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;

class QueryBuilder extends DBALQueryBuilder
{
    /**
     * @var string[]
     */
    private $states = [];

    /**
     * @var QuerySelection
     */
    private $selection;

    public function __construct(Connection $connection, QuerySelection $selection)
    {
        parent::__construct($connection);
        $this->selection = $selection;
    }

    public function addState(string $state): void
    {
        $this->states[] = $state;
    }

    public function hasState(string $state): bool
    {
        return in_array($state, $this->states, true);
    }

    public function getSelection(): QuerySelection
    {
        return $this->selection;
    }
}