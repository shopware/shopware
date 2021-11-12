<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;

abstract class PageLoadedHook extends Hook implements SalesChannelContextAware
{
    public function getServiceIds(): array
    {
        return [
            // ToDo add common services here
        ];
    }
}
