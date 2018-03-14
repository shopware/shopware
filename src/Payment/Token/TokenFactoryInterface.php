<?php declare(strict_types=1);

namespace Shopware\Payment\Token;

interface TokenFactoryInterface
{
    public function generateToken(
        string $paymentMethodId,
        string $transactionId,
        \DateTime $expires = null,
        int $length = 60
    ): string;

    public function validateToken(string $row): TokenStruct;

    public function invalidateToken(string $tokenId): bool;
}
