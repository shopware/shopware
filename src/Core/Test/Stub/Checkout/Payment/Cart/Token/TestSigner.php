<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;

/**
 * @internal
 */
class TestSigner implements Signer
{
    public function algorithmId(): string
    {
        return 'none';
    }

    public function sign(string $payload, Key $key): string
    {
        return 'empty';
    }

    public function verify(string $expected, string $payload, Key $key): bool
    {
        return $expected === '';
    }
}
