<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<LandingPageEntity>
 */
#[Package('discovery')]
class LandingPageRouteResponse extends StoreApiResponse
{
    public function getLandingPage(): LandingPageEntity
    {
        return $this->object;
    }
}
