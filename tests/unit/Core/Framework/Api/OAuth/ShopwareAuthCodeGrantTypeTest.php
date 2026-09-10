<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\ShopwareAuthCodeGrantType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareAuthCodeGrantType::class)]
class ShopwareAuthCodeGrantTypeTest extends TestCase
{
    private ShopwareAuthCodeGrantType $grant;

    protected function setUp(): void
    {
        $this->grant = new ShopwareAuthCodeGrantType(
            static::createStub(AuthCodeRepositoryInterface::class),
            static::createStub(RefreshTokenRepositoryInterface::class),
            new \DateInterval('PT5M'),
        );
    }

    public function testIdentifier(): void
    {
        static::assertSame('authorization_code', $this->grant->getIdentifier());
        static::assertSame(ShopwareAuthCodeGrantType::TYPE, $this->grant->getIdentifier());
    }

    #[DataProvider('rejectedCodeChallengeMethodProvider')]
    public function testRejectsEveryCodeChallengeMethodExceptS256(?string $method): void
    {
        $query = ['response_type' => 'code', 'client_id' => 'shopware-cli', 'code_challenge' => str_repeat('a', 43)];
        if ($method !== null) {
            $query['code_challenge_method'] = $method;
        }

        $this->expectExceptionObject(
            OAuthServerException::invalidRequest('code_challenge_method', 'Code challenge method must be `S256`.')
        );

        $this->grant->validateAuthorizationRequest((new ServerRequest('GET', '/api/oauth/authorize'))->withQueryParams($query));
    }

    public function testS256RequestsAreHandedToTheLibraryValidation(): void
    {
        // no client_id is given, so the library validation must be reached and fail on that parameter
        $request = (new ServerRequest('GET', '/api/oauth/authorize'))->withQueryParams([
            'response_type' => 'code',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);

        $this->expectExceptionObject(OAuthServerException::invalidRequest('client_id'));

        $this->grant->validateAuthorizationRequest($request);
    }

    public function testInvalidRedirectIsWrappedForTheApiClient(): void
    {
        $clients = static::createStub(ClientRepositoryInterface::class);
        $clients->method('getClientEntity')->willReturn(new ApiClient(
            'shopware-cli',
            writeAccess: true,
            confidential: false,
            redirectUris: ['http://127.0.0.1/callback'],
        ));
        $this->grant->setClientRepository($clients);
        $request = (new ServerRequest('GET', '/api/oauth/authorize'))->withQueryParams([
            'response_type' => 'code',
            'client_id' => 'shopware-cli',
            'redirect_uri' => 'https://example.invalid/callback',
            'code_challenge_method' => 'S256',
        ]);

        $this->expectExceptionObject(ApiException::invalidOAuthRedirectUri(OAuthServerException::invalidClient($request)));

        $this->grant->validateAuthorizationRequest($request);
    }

    public function testRegisteredLoopbackRedirectStillAcceptsAnyPort(): void
    {
        $clients = static::createStub(ClientRepositoryInterface::class);
        $clients->method('getClientEntity')->willReturn(new ApiClient(
            'shopware-cli',
            writeAccess: true,
            confidential: false,
            redirectUris: ['http://127.0.0.1/callback'],
        ));
        $this->grant->setClientRepository($clients);
        $this->grant->setScopeRepository(static::createStub(ScopeRepositoryInterface::class));
        $this->grant->setDefaultScope('');
        $request = (new ServerRequest('GET', '/api/oauth/authorize'))->withQueryParams([
            'response_type' => 'code',
            'client_id' => 'shopware-cli',
            'redirect_uri' => 'http://127.0.0.1:54321/callback',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);

        $authorization = $this->grant->validateAuthorizationRequest($request);

        static::assertSame('http://127.0.0.1:54321/callback', $authorization->getRedirectUri());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function rejectedCodeChallengeMethodProvider(): iterable
    {
        yield 'plain is forbidden by RFC 9700' => ['plain'];
        yield 'missing method would default to plain' => [null];
        yield 'unknown method' => ['MD5'];
        yield 'lower case s256 is not the registered method' => ['s256'];
    }
}
