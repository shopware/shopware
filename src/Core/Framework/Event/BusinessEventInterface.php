<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\EventDataCollection;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use FlowEventAware instead.
 *
 * Tag for events that can be used in the action/action system
 */
interface BusinessEventInterface extends FlowEventAware
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}
