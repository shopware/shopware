<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\TokenService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\LoginException;
use Shopware\Administration\Login\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExternalTokenService::class)]
class ExternalTokenServiceTest extends TestCase
{
    public function testGetUserTokenShouldThrowException(): void
    {
        $this->expectExceptionObject(LoginException::configurationNotFound());

        $this->createExternalTokenService('token', true)->getUserToken('any');
    }

    public function testGetUserToken(): void
    {
        $token = (new FakeTokenGenerator())->generate();
        $result = $this->createExternalTokenService($token)->getUserToken('code');

        static::assertSame($token, $result->idToken);
        static::assertSame('access_token', $result->accessToken);
        static::assertSame('refresh_token', $result->refreshToken);
        static::assertSame('Bearer', $result->tokenType);
        static::assertSame(3600, $result->expiresIn);
    }

    public function testGetUserTokenByRefreshTokenShouldThrowException(): void
    {
        $this->expectExceptionObject(LoginException::configurationNotFound());

        $this->createExternalTokenService('token', true)->getUserTokenByRefreshToken('any');
    }

    public function testGetUserTokenByRefreshToken(): void
    {
        $token = (new FakeTokenGenerator())->generate();
        $result = $this->createExternalTokenService($token)->getUserTokenByRefreshToken('refresh_token');

        static::assertSame($token, $result->idToken);
        static::assertSame('access_token', $result->accessToken);
        static::assertSame('refresh_token', $result->refreshToken);
        static::assertSame('Bearer', $result->tokenType);
        static::assertSame(3600, $result->expiresIn);
    }

    private function createExternalTokenService(string $token, bool $withEmptyConfig = false): ExternalTokenService
    {
        $responseInterface = $this->createMock(ResponseInterface::class);
        $responseInterface->method('getContent')->willReturn(
            \json_encode(
                [
                    'id_token' => $token,
                    'access_token' => 'access_token',
                    'refresh_token' => 'refresh_token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                    'scope' => 'scope',
                ]
            )
        );

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($responseInterface);

        $config = [
            'use_default' => false,
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_uri' => 'http://redirect.uri',
            'base_url' => 'http://base.uri',
            'session_key' => 'session_key',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/json.json',
            'scope' => 'scope',
            'register_url' => 'https://register.url',
        ];

        if ($withEmptyConfig) {
            $config = [];
        } else {
            $responseInterface->expects($this->once())->method('getContent');
            $client->expects($this->once())->method('request');
        }

        $loginConfigService = new LoginConfigService(
            // @phpstan-ignore argument.type
            $config,
            '',
            ''
        );

        return new ExternalTokenService($client, $loginConfigService);
    }
}
