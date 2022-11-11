<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package core
 */
class StoreApiRouteCacheKeyEvent extends Event
{
    /**
     * @var array<mixed>
     */
    protected array $parts;

    protected Request $request;

    protected SalesChannelContext $context;

    protected ?Criteria $criteria;

    private bool $disableCaching = false;

    /**
     * @param array<mixed> $parts
     */
    public function __construct(array $parts, Request $request, SalesChannelContext $context, ?Criteria $criteria)
    {
        $this->parts = $parts;
        $this->request = $request;
        $this->context = $context;
        $this->criteria = $criteria;
    }

    /**
     * @return array<mixed>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getCriteria(): ?Criteria
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
