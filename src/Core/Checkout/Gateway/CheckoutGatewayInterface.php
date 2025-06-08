<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway;

use Shopware\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface CheckoutGatewayInterface
{
    public function process(CheckoutGatewayPayloadStruct $payload): CheckoutGatewayResponse;
}
