<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Signer\Key;

/**
 * @internal
 */
class TestKey implements Key
{
    public function contents(): string
    {
        return 'test';
    }

    public function passphrase(): string
    {
        return '';
    }
}
