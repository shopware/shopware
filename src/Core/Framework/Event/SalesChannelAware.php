<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface SalesChannelAware extends FlowEventAware
{
    public function getSalesChannelId(): string;
}
