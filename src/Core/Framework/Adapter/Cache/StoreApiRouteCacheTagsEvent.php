<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class StoreApiRouteCacheTagsEvent extends Event
{
    protected array $tags;

    protected Request $request;

    protected SalesChannelContext $context;

    protected ?Criteria $criteria;

    private StoreApiResponse $response;

    public function __construct(array $tags, Request $request, StoreApiResponse $response, SalesChannelContext $context, ?Criteria $criteria)
    {
        $this->tags = $tags;
        $this->request = $request;
        $this->context = $context;
        $this->criteria = $criteria;
        $this->response = $response;
    }

    public function getTags(): array
    {
        return $this->tags;
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

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function addTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }

    public function getSalesChannelId(): string
    {
        return $this->context->getSalesChannelId();
    }

    public function getResponse(): StoreApiResponse
    {
        return $this->response;
    }
}
