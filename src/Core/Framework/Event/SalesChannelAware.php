<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - extends of FlowEventAware will be removed
 */
#[Package('business-ops')]
interface SalesChannelAware extends FlowEventAware
{
    public function getSalesChannelId(): string;
}
