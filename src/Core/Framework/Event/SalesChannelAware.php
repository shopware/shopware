<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface SalesChannelAware
{
    public function getSalesChannelId(): string;
}
