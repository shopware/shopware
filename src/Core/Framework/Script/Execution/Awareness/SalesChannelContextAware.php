<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Can be implemented by hooks to provide services with the sales channel context.
 * The services can inject the context beforehand and provide a narrow API to the developer.
 *
 * @internal
 */
#[Package('core')]
interface SalesChannelContextAware
{
    public function getSalesChannelContext(): SalesChannelContext;
}
