<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class ShopUserTokenStruct extends Struct
{
    /**
     * @var string
     */
    protected $token;

    /**
     * @var \DateTimeInterface
     */
    protected $expirationDate;

    public function getToken(): string
    {
        return $this->token;
    }

    public function getApiAlias(): string
    {
        return 'store_shop_user_token';
    }
}
