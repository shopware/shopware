<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token\JWTMock;

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
        return '';
    }

    public function verify(string $expected, string $payload, Key $key): bool
    {
        return $expected === '';
    }
}
