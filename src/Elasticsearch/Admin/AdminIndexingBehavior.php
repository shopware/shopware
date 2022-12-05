<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

class AdminIndexingBehavior
{
    protected bool $noQueue = false;

    /**
     * @var array<int, string|null>
     */
    protected array $entities = [];

    /**
     * @param array<int, string|null> $entities
     */
    public function __construct(bool $noQueue = false, array $entities = [])
    {
        $this->noQueue = $noQueue;
        $this->entities = $entities;
    }

    public function getNoQueue(): bool
    {
        return $this->noQueue;
    }

    /**
     * @return array<int, string|null>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
