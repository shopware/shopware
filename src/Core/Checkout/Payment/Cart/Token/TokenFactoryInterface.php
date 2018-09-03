<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStruct;
use Shopware\Core\Framework\Context;

interface TokenFactoryInterface
{
    public function generateToken(OrderTransactionStruct $transaction, Context $context, int $expiresInSeconds = 1800): string;

    public function parseToken(string $token, Context $context): TokenStruct;

    public function invalidateToken(string $tokenId, Context $context): bool;
}
