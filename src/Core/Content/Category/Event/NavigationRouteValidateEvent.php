<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class NavigationRouteValidateEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    protected bool $valid = false;

    public function __construct(
        protected string $activeId, 
        protected ?string $path, 
        protected SalesChannelContext $salesChannelContext
    ) {}

    public function getActiveId(): string
    {
        return $this->activeId;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }
}
