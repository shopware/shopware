<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Cart\Token;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionBasicStruct;

interface PaymentTransactionTokenFactoryInterface
{
    public function generateToken(OrderTransactionBasicStruct $transaction, Context $context): string;

    public function validateToken(string $token, Context $context): TokenStruct;

    public function invalidateToken(string $tokenId, Context $context): bool;
}
