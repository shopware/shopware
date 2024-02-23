<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
interface EventDataType
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
