<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\SalesChannelContextAware;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class SalesChannelContextStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof SalesChannelContextAware) {
            return $stored;
        }

        $stored[SalesChannelContextAware::SALES_CHANNEL_CONTEXT] = $event->getSalesChannelContext();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(SalesChannelContextAware::SALES_CHANNEL_CONTEXT)) {
            return;
        }

        $storable->setData(SalesChannelContextAware::SALES_CHANNEL_CONTEXT, $storable->getStore(SalesChannelContextAware::SALES_CHANNEL_CONTEXT));
    }
}
