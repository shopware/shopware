<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface TokenFactoryInterfaceV2
{
    public function generateToken(TokenStruct $tokenStruct): string;

    public function parseToken(string $token): TokenStruct;

    public function invalidateToken(string $tokenId): bool;
}
