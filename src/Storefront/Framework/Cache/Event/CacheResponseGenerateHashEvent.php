<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('storefront')]
class CacheResponseGenerateHashEvent extends Event
{
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private array $parts
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getParts(): array
    {
        return $this->parts;
    }

    public function setParts(array $parts): void
    {
        $this->parts = $parts;
    }

    public function addPart(string $part): self
    {
        $this->parts[] = $part;

        return $this;
    }
}
