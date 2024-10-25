<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
class SalesChannelEntrypointEvent implements SalesChannelAware
{
    /**
     * @var array<string>
     */
    private array $entrypoints;

    public function __construct(
        private readonly SalesChannelEntity $salesChannel,
        private readonly ?SalesChannelContext $salesChannelContext,
    ) {
        $this->entrypoints = [];
    }

    public function addEntrypointType(string $entrypointType): void
    {
        $this->entrypoints[] = $entrypointType;
    }

    /**
     * @return array|string[]
     */
    public function getEntrypoints(): array
    {
        return $this->entrypoints;
    }

    public function getSalesChannelContext(): ?SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannel->getId();
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }
}
