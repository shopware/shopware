<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Cart\Token;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionBasicStruct;

interface PaymentTransactionTokenFactoryInterface
{
    public function generateToken(OrderTransactionBasicStruct $transaction, ApplicationContext $context): string;

    public function validateToken(string $token, ApplicationContext $context): TokenStruct;

    public function invalidateToken(string $tokenId, ApplicationContext $context): bool;
}
