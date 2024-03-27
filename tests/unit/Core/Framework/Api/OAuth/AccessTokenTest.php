<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\CryptKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\AccessToken;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(AccessToken::class)]
class AccessTokenTest extends TestCase
{
    public function testToken(): void
    {
        $client = new ApiClient('administration', true, 'test');
        $token = new AccessToken(
            $client,
            [],
            'test'
        );

        static::assertEquals('test', $token->getUserIdentifier());
        static::assertEquals('administration', $token->getClient()->getIdentifier());
        static::assertCount(0, $token->getScopes());

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('testtesttesttesttesttesttesttesttesttesttesttesttesttesttest')
        );

        $token->addScope(new WriteScope());
        $token->setClient($client);
        $token->setPrivateKey(new FakeCryptKey($config));
        $token->setIdentifier('administration');
        static::assertEquals('administration', $token->getIdentifier());
        static::assertSame($client, $token->getClient());
        $token->setExpiryDateTime(new \DateTimeImmutable());

        static::assertNotEmpty($token->__toString());
    }

    /**
     * @deprecated tag:v6.7.0 - test will be removed
     */
    #[DoesNotPerformAssertions]
    #[DisabledFeatures(features: ['v6.7.0.0'])]
    public function testTokenWithOldKey(): void
    {
        $client = new ApiClient('administration', true, 'test');
        $token = new AccessToken(
            $client,
            [],
            'test'
        );

        $privateKey = $this->createMock(CryptKey::class);
        $privateKey->method('getKeyContents')->willReturn('test');

        $token->setPrivateKey($privateKey);

        $token->initJwtConfiguration();
    }
}
