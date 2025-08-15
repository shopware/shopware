<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\Struct\CookieHash;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<CookieHash>
 */
#[Package('framework')]
class CookieHashRouteResponse extends StoreApiResponse
{
    public function __construct(string $cookieHash)
    {
        parent::__construct(new CookieHash($cookieHash));
    }

    public function getCookieHash(): string
    {
        return $this->object->cookieHash;
    }
}
