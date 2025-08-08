<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class CategoryLevelLoaderCacheKeyEvent extends Event implements ShopwareSalesChannelEvent
{
    private bool $shouldCache = true;

    /**
     * @param array<string, mixed> $parts
     */
    public function __construct(
        private array $parts,
        private string $rootId,
        private int $depth,
        private SalesChannelContext $context,
        private Criteria $criteria
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getRootId(): string
    {
        return $this->rootId;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    /**
     * @param array<string, mixed> $parts
     */
    public function setParts(array $parts): void
    {
        $this->parts = $parts;
    }

    public function addPart(string $key, string $part): void
    {
        $this->parts[$key] = $part;
    }

    public function removePart(string $part): void
    {
        unset($this->parts[$part]);
    }

    public function getSalesChannelId(): string
    {
        return $this->context->getSalesChannelId();
    }

    public function disableCaching(): void
    {
        $this->shouldCache = false;
    }

    public function shouldCache(): bool
    {
        return $this->shouldCache;
    }
}
