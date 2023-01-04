<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use FlowEventAware instead.
 *
 * Tag for events that can be used in the action/action system
 */
#[Package('business-ops')]
interface BusinessEventInterface extends FlowEventAware
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}
