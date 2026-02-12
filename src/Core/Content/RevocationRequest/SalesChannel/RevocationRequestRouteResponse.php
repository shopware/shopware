<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<RevocationRequestFormRouteResponseStruct>
 */
#[Package('after-sales')]
class RevocationRequestRouteResponse extends StoreApiResponse
{
    public function getResult(): RevocationRequestFormRouteResponseStruct
    {
        return $this->object;
    }
}
