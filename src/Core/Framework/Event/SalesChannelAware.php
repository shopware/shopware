<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
#[IsFlowEventAware]
interface SalesChannelAware
{
    public function getSalesChannelId(): string;
}
