<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class AccessTokenStruct extends Struct
{
    /**
     * @var ShopUserTokenStruct
     */
    protected $shopUserToken;

    /**
     * @var string
     */
    protected $shopSecret;

    public function setShopUserToken(ShopUserTokenStruct $shopUserToken): void
    {
        $this->shopUserToken = $shopUserToken;
    }

    public function getShopUserToken(): ShopUserTokenStruct
    {
        return $this->shopUserToken;
    }

    public function getShopSecret(): string
    {
        return $this->shopSecret;
    }

    public function getApiAlias(): string
    {
        return 'store_access_token';
    }
}
