<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface OrderAware extends FlowEventAware
{
    public function getOrderId(): string;
}
