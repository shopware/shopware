<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
interface EventDataType
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
