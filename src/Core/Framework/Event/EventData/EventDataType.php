<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

interface EventDataType
{
    public function toArray(): array;
}
