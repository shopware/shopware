<?php declare(strict_types=1);

namespace Shopware\Core\Content\CancellationRequest\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('after-sales')]
class CancellationRequestRouteResponse extends StoreApiResponse
{
    public function getResult(): CancellationRequestFormRouteResponseStruct
    {
        return $this->object;
    }
}
