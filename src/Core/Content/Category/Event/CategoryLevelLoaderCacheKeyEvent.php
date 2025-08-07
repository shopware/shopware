<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class CategoryLevelLoaderCacheKeyEvent extends Event
{
    private bool $disableCaching = false;

    /**
     * @param array<mixed> $parts
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
     * @return array<mixed>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
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
     * @param array<int, bool|string> $parts
     */
    public function setParts(array $parts): void
    {
        $this->parts = $parts;
    }

    public function addPart(string $part): void
    {
        $this->parts[] = $part;
    }

    public function getSalesChannelId(): string
    {
        return $this->context->getSalesChannelId();
    }

    public function disableCaching(): void
    {
        $this->disableCaching = true;
    }

    public function shouldCache(): bool
    {
        return !$this->disableCaching;
    }
}
