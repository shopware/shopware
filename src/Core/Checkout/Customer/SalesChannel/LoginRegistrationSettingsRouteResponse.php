<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<LoginRegistrationSettings>
 */
#[Package('checkout')]
class LoginRegistrationSettingsRouteResponse extends StoreApiResponse
{
    public function getSettings(): LoginRegistrationSettings
    {
        return $this->object;
    }
}
