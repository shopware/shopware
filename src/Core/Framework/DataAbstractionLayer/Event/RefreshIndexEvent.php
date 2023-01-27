<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('system-settings')]
class RefreshIndexEvent extends Event
{
    /**
     * @param array<int, string|null> $skipEntities
     * @param array<int, string|null> $onlyEntities
     */
    public function __construct(
        private readonly bool $noQueue = false,
        private readonly array $skipEntities = [],
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
