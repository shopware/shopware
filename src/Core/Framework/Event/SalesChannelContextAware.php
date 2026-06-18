<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[IsFlowEventAware]
interface SalesChannelContextAware extends SalesChannelAware
{
    public const SALES_CHANNEL_CONTEXT = 'salesChannelContext';

    public const SALES_CHANNEL_DOMAIN_ID = 'salesChannelDomainId';

    public const SALES_CHANNEL_CUSTOMER_ID = 'salesChannelCustomerId';

    public function getSalesChannelContext(): SalesChannelContext;
}
