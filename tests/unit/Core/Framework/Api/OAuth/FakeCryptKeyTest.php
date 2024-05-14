<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;

/**
 * @internal
 */
#[CoversClass(FakeCryptKey::class)]
class FakeCryptKeyTest extends TestCase
{
    public function testConstructor(): void
    {
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText('test'));
        $fakeCryptKey = new FakeCryptKey($configuration);
        static::assertEquals('', $fakeCryptKey->getKeyContents());
        static::assertEquals('', $fakeCryptKey->getKeyPath());
        static::assertEquals('', $fakeCryptKey->getPassPhrase());
        static::assertSame($configuration, $fakeCryptKey->configuration);
    }
}
