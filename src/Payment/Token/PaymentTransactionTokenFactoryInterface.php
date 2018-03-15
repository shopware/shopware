<?php declare(strict_types=1);

namespace Shopware\Payment\Token;

use Shopware\Api\Order\Struct\OrderTransactionBasicStruct;

interface PaymentTransactionTokenFactoryInterface
{
    public function generateToken(OrderTransactionBasicStruct $transaction): string;

    public function validateToken(string $token): TokenStruct;

    public function invalidateToken(string $tokenId): bool;
}
