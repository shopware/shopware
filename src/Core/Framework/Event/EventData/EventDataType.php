<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

/**
 * @package business-ops
 */
interface EventDataType
{
    public function toArray(): array;
}
