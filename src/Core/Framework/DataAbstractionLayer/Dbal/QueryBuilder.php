<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;

class QueryBuilder extends DBALQueryBuilder
{
    /**
     * @var string[]
     */
    private $states = [];

    /**
     * @var string|null
     */
    private $title;

    public function addState(string $state): void
    {
        $this->states[$state] = $state;
    }

    public function removeState(string $state): void
    {
        unset($this->states[$state]);
    }

    public function hasState(string $state): bool
    {
        return \in_array($state, $this->states, true);
    }

    public function getStates(): array
    {
        return $this->states;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getSQL()
    {
        $sql = parent::getSQL();

        if ($this->getTitle()) {
            $sql = '# ' . $this->title . \PHP_EOL . $sql;
        }

        return $sql;
    }
}
