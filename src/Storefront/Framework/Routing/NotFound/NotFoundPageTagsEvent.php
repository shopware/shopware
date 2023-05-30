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
    /**
     * @param array<string> $tags
     */
    public function __construct(
        private array $tags,
        private readonly Request $request,
        private readonly SalesChannelContext $context
    ) {
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

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<string> $tags
     */
    public function addTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }
}
