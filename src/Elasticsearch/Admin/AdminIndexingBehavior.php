<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AdminIndexingBehavior
{
    /**
     * @param array<int, string|null> $skipEntities
     * @param array<int, string|null> $onlyEntities
     */
    public function __construct(
        protected bool $noQueue = false,
        protected array $skipEntities = [],
        private readonly array $onlyEntities = []
    ) {
    }

    public function getNoQueue(): bool
    {
        return $this->noQueue;
    }

    /**
     * @return array<int, string|null>
     */
    public function getSkipEntities(): array
    {
        return $this->skipEntities;
    }

    /**
     * @return array<int, string|null>
     */
    public function getOnlyEntities(): array
    {
        return $this->onlyEntities;
    }
}
