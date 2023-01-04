<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\NotFound;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class NotFoundPageTagsEvent implements ShopwareEvent
{
    private array $tags;

    private Request $request;

    private SalesChannelContext $context;

    public function __construct(array $tags, Request $request, SalesChannelContext $context)
    {
        $this->tags = $tags;
        $this->request = $request;
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function addTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }
}
