<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class AccessTokenStruct extends Struct
{
    public function __construct(
        protected ShopUserTokenStruct $shopUserToken,
        protected ?string $shopSecret = null,
    ) {
    }

    public function getShopUserToken(): ShopUserTokenStruct
    {
        return $this->shopUserToken;
    }

    public function getShopSecret(): ?string
    {
        return $this->shopSecret;
    }

    public function getApiAlias(): string
    {
        return 'store_access_token';
    }
}
