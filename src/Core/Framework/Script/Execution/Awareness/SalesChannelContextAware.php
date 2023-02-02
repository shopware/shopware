<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Can be implemented by hooks to provide services with the sales channel context.
 * The services can inject the context beforehand and provide a narrow API to the developer.
 *
 * @deprecated tag:v6.5.0 will be marked internal
 */
interface SalesChannelContextAware
{
    public function getSalesChannelContext(): SalesChannelContext;
}
