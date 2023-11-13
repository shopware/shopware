<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface FlowEventAware extends ShopwareEvent
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}
