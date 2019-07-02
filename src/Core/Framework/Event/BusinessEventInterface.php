<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\EventDataCollection;

/**
 * Tag for events that can be used in the action/action system
 */
interface BusinessEventInterface extends ShopwareEvent
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}
