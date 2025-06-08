<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Event\EventData\EventDataType;

/**
 * @internal
 */
class InvalidEventType implements EventDataType
{
    public function toArray(): array
    {
        return [
            'type' => 'invalid',
        ];
    }
}
