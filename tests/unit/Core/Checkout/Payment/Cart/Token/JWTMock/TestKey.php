<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token\JWTMock;

use Lcobucci\JWT\Signer\Key;

/**
 * @internal
 */
class TestKey implements Key
{
    public function contents(): string
    {
        return '';
    }

    public function passphrase(): string
    {
        return '';
    }
}
