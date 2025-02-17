<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\AccessToken;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;

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

        $config = JWTConfigurationFactory::createJWTConfiguration();
        $token->addScope(new WriteScope());
        $token->setClient($client);
        $token->setPrivateKey(new FakeCryptKey($config));
        $token->setIdentifier('administration');
        static::assertEquals('administration', $token->getIdentifier());
        static::assertSame($client, $token->getClient());
        $token->setExpiryDateTime(new \DateTimeImmutable());

        static::assertNotEmpty($token->toString());
    }
}
