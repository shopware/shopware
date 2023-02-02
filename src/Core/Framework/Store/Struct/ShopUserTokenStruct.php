<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class ShopUserTokenStruct extends Struct
{
    public function __construct(
        protected string $token,
        protected \DateTimeInterface $expirationDate,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpirationDate(): \DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function getApiAlias(): string
    {
        return 'store_shop_user_token';
    }
}
