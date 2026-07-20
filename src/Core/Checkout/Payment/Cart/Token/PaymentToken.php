<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Framework\JWT\Struct\JWTStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PaymentToken extends JWTStruct
{
    public string $paymentMethodId;

    public string $transactionId;

    public ?string $finishUrl = null;

    public ?string $errorUrl = null;

    public string $salesChannelId;
}
