<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener;
use Shopware\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Shopware\Core\Framework\Api\OAuth\GrantTypeFactory;
use Shopware\Core\Framework\Api\OAuth\OAuthResource;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiAuthenticationListener::class)]
class ApiAuthenticationListenerTest extends TestCase
{
    private const RESOURCE = 'https://shop.example/api/_mcp';

    private Configuration $jwtConfiguration;

    protected function setUp(): void
    {
        $key = InMemory::plainText('testtesttesttesttesttesttesttesttesttesttesttesttesttesttest');
        $this->jwtConfiguration = Configuration::forSymmetricSigner(new Sha256(), $key)
            ->withValidationConstraints(new SignedWith(new Sha256(), $key));
    }

    public function testMcpBoundTokenIsAcceptedOnTheMcpEndpoint(): void
    {
        $request = $this->createRequest('api.mcp.endpoint', [self::RESOURCE]);

        $this->createListener()->validateRequest($this->createEvent($request, HttpKernelInterface::MAIN_REQUEST));

        static::assertSame(self::RESOURCE, $request->attributes->get(OAuthResource::REQUEST_ATTRIBUTE));
    }

    #[DataProvider('otherRouteProvider')]
    public function testMcpBoundTokenIsRejectedOnEveryOtherMainRequest(string $route): void
    {
        $request = $this->createRequest($route, [self::RESOURCE]);

        try {
            $this->createListener()->validateRequest($this->createEvent($request, HttpKernelInterface::MAIN_REQUEST));
            static::fail('The MCP bound token was accepted on ' . $route);
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_token', $exception->getErrorType());
            static::assertSame(401, $exception->getHttpStatusCode());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherRouteProvider(): iterable
    {
        yield 'info' => ['api.info.me'];
        yield 'consent approval' => ['api.oauth.authorize.approve'];
        yield 'app script' => ['api.script_endpoint'];
    }

    public function testSubrequestOfAnMcpRequestKeepsTheMcpBoundToken(): void
    {
        $request = $this->createRequest('api.script_endpoint', [self::RESOURCE]);

        $this->createListener()->validateRequest($this->createEvent($request, HttpKernelInterface::SUB_REQUEST));

        static::assertSame(self::RESOURCE, $request->attributes->get(OAuthResource::REQUEST_ATTRIBUTE));
    }

    public function testTokenWithoutResourceIsAcceptedEverywhere(): void
    {
        $request = $this->createRequest('api.info.me', []);

        $this->createListener()->validateRequest($this->createEvent($request, HttpKernelInterface::MAIN_REQUEST));

        static::assertSame('shopware-cli', $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID));
        static::assertFalse($request->attributes->has(OAuthResource::REQUEST_ATTRIBUTE));
    }

    /**
     * @param list<non-empty-string> $resources
     */
    private function createRequest(string $route, array $resources): Request
    {
        $now = new \DateTimeImmutable();
        $jwt = $this->jwtConfiguration->builder()
            ->permittedFor('shopware-cli', ...$resources)
            ->identifiedBy('token-id')
            ->issuedAt($now)
            ->expiresAt($now->modify('+10 minutes'))
            ->relatedTo('shopware-cli')
            ->withClaim('scopes', ['write'])
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey())
            ->toString();

        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);
        $request->attributes->set('_route', $route);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);

        return $request;
    }

    private function createEvent(Request $request, int $requestType): ControllerEvent
    {
        return new ControllerEvent(static::createStub(HttpKernelInterface::class), static fn () => null, $request, $requestType);
    }

    private function createListener(): ApiAuthenticationListener
    {
        $clock = new NativeClock();
        $loginConfigService = static::createStub(LoginConfigService::class);
        $externalTokenService = new ExternalTokenService(static::createStub(HttpClientInterface::class), $loginConfigService);
        $userService = new UserService(
            static::createStub(Connection::class),
            new IdTokenParser(
                new PublicKeyLoader(static::createStub(HttpClientInterface::class), $loginConfigService, new ArrayAdapter()),
                $loginConfigService,
                $clock
            ),
            StaticEntityRepository::of(UserCollection::class, []),
            $externalTokenService,
            $clock,
        );

        return new ApiAuthenticationListener(
            new SymfonyBearerTokenValidator(
                static::createStub(AccessTokenRepositoryInterface::class),
                static::createStub(Connection::class),
                $this->jwtConfiguration
            ),
            static::createStub(AuthorizationServer::class),
            new GrantTypeFactory(
                static::createStub(UserRepositoryInterface::class),
                static::createStub(RefreshTokenRepository::class),
                static::createStub(AuthCodeRepositoryInterface::class),
                $userService,
                $externalTokenService,
                $clock,
                new PublicClientRegistry([], 'prod'),
            ),
            new RouteScopeRegistry([new ApiRouteScope()]),
        );
    }
}
