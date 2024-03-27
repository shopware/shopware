<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Signer\Hmac\Sha256 as Hmac256;
use Lcobucci\JWT\Signer\Rsa\Sha256 as Rsa256;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(JWTConfigurationFactory::class)]
class JWTConfigurationFactoryTest extends TestCase
{
    public function testCreateFromAppEnv(): void
    {
        $config = JWTConfigurationFactory::createJWTConfiguration(true, 'test', '', 'test');

        static::assertInstanceOf(Hmac256::class, $config->signer());
    }

    #[DisabledFeatures(features: ['v6.7.0.0'])]
    public function testCreateFromFiles(): void
    {
        $config = JWTConfigurationFactory::createJWTConfiguration(false, 'file://' . __DIR__ . '/_fixtures/private.txt', 'shopware', 'file://' . __DIR__ . '/_fixtures/public.txt');

        static::assertInstanceOf(Rsa256::class, $config->signer());
    }

    #[DisabledFeatures(features: ['v6.7.0.0'])]
    public function testCreateFromString(): void
    {
        $privateKey = (string) file_get_contents(__DIR__ . '/_fixtures/private.txt');
        $publicKey = (string) file_get_contents(__DIR__ . '/_fixtures/public.txt');
        static::assertNotEmpty($privateKey);
        static::assertNotEmpty($publicKey);

        $config = JWTConfigurationFactory::createJWTConfiguration(false, $privateKey, 'shopware', $publicKey);

        static::assertInstanceOf(Rsa256::class, $config->signer());
    }
}
