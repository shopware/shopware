<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * @deprecated tag:v6.3.0 will be removed. use TokenFactoryInterfaceV2 instead
 */
interface TokenFactoryInterface
{
    public function generateToken(
        OrderTransactionEntity $transaction,
        ?string $finishUrl = null,
        int $expiresInSeconds = 1800
    ): string;

    public function parseToken(string $token): TokenStruct;

    public function invalidateToken(string $tokenId): bool;
}
