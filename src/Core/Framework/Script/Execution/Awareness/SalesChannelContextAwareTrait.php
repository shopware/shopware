<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait SalesChannelContextAwareTrait
{
    protected SalesChannelContext $salesChannelContext;

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
