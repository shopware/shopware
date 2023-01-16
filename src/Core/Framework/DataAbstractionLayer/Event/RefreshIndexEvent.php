<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package system-settings
 *
 * @internal
 */
class RefreshIndexEvent extends Event
{
    private bool $noQueue;

    /**
     * @var array<int, string|null>
     */
    private array $skipEntities;

    /**
     * @var array<int, string|null>
     */
    private array $onlyEntities;

    /**
     * @param array<int, string|null> $skipEntities
     * @param array<int, string|null> $onlyEntities
     */
    public function __construct(bool $noQueue = false, array $skipEntities = [], array $onlyEntities = [])
    {
        $this->noQueue = $noQueue;
        $this->skipEntities = $skipEntities;
        $this->onlyEntities = $onlyEntities;
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
