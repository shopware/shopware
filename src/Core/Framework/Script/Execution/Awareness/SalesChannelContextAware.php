<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SalesChannelContextAware
{
    public function getSalesChannelContext(): SalesChannelContext;
}
