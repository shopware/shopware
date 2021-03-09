<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
interface SalesChannelContextServiceInterface
{
    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext;
}
