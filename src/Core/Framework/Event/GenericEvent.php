<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface GenericEvent
{
    public function getName(): string;
}
