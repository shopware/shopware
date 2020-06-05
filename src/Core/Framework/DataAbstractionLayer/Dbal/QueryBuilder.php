<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;

class QueryBuilder extends DBALQueryBuilder
{
    /**
     * @var string[]
     */
    private $states = [];

    public function addState(string $state): void
    {
        $this->states[] = $state;
    }

    public function hasState(string $state): bool
    {
        return \in_array($state, $this->states, true);
    }

    public function getStates(): array
    {
        return $this->states;
    }
}
