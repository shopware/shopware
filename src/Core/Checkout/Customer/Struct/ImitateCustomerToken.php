<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Struct;

use Shopware\Core\Framework\JWT\Struct\JWTStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ImitateCustomerToken extends JWTStruct
{
    public string $salesChannelId;

    public string $customerId;
}
