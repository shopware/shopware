<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<CookieGroupCollection>
 */
#[Package('framework')]
class CookieRouteResponse extends StoreApiResponse
{
    public function getCookieGroups(): CookieGroupCollection
    {
        return $this->object;
    }
}
