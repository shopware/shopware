<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface CustomerAware extends FlowEventAware
{
    public function getCustomerId(): string;
}
