<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SalesChannelContext>
 */
#[Package('framework')]
class ContextLoadRouteResponse extends StoreApiResponse
{
    public function getContext(): SalesChannelContext
    {
        return $this->object;
    }
}
