<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class SyncBehavior
{
    /**
     * @param list<string> $skipIndexers
     * @param list<string> $onlyIndexers
     */
    public function __construct(
        protected ?string $indexingBehavior = null,
        protected array $skipIndexers = [],
        protected array $onlyIndexers = []
    ) {
    }

    public function getIndexingBehavior(): ?string
    {
        return $this->indexingBehavior;
    }

    /**
     * @return list<string>
     */
    public function getSkipIndexers(): array
    {
        return $this->skipIndexers;
    }

    /**
     * @return list<string>
     */
    public function getOnlyIndexers(): array
    {
        return $this->onlyIndexers;
    }
}
