<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenGenerator;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextHandoffGenerateRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextHandoffGenerateRoute::class)]
class ContextHandoffGenerateRouteTest extends TestCase
{
    private const CONTEXT_TOKEN = 'the-current-context-token';
    private const CLIENT_IP = '10.11.12.13';

    private string $salesChannelId;

    private ContextHandoffTokenGenerator $tokenGenerator;

    private ContextHandoffTokenStore&Stub $tokenStore;

    private MockClock $clock;

    private RateLimiter&Stub $rateLimiter;

    private RequestStack $requestStack;

    private ContextHandoffGenerateRoute $route;

    protected function setUp(): void
    {
        $this->salesChannelId = Uuid::randomHex();
        $this->tokenGenerator = new ContextHandoffTokenGenerator(
            JWTConfigurationFactory::createJWTConfiguration(),
            new DataValidator(Validation::createValidator())
        );
        $this->tokenStore = static::createStub(ContextHandoffTokenStore::class);
        $this->clock = new MockClock();
        $this->rateLimiter = static::createStub(RateLimiter::class);
        $this->requestStack = new RequestStack();
        $this->requestStack->push(new Request(server: ['REMOTE_ADDR' => self::CLIENT_IP]));

        $this->route = $this->createRoute();
    }

    public function testGeneratedTokenReferencesTheCurrentContext(): void
    {
        $tokenStore = $this->createMock(ContextHandoffTokenStore::class);

        $storedJti = null;
        $tokenStore->expects($this->once())
            ->method('store')
            ->with(
                static::callback(static function (string $jti) use (&$storedJti): bool {
                    $storedJti = $jti;

                    return true;
                }),
                self::CONTEXT_TOKEN,
                static::anything()
            );

        $handoffToken = $this->tokenGenerator->decode(
            $this->createRoute(tokenStore: $tokenStore)->generate($this->createContext())->getHandoffToken()
        );

        static::assertSame($this->salesChannelId, $handoffToken->salesChannelId);
        static::assertSame($storedJti, $handoffToken->jti);
    }

    public function testStoredEntryExpiresWithTheHandoffToken(): void
    {
        $tokenStore = $this->createMock(ContextHandoffTokenStore::class);
        $tokenStore->expects($this->once())
            ->method('store')
            ->with(
                static::anything(),
                self::CONTEXT_TOKEN,
                static::callback(static fn (\DateTimeInterface $expiresAt): bool => $expiresAt->format(\DateTimeInterface::RFC3339) === '2026-08-18T12:01:00+00:00')
            );

        $route = $this->createRoute(tokenStore: $tokenStore, clock: new MockClock(new \DateTimeImmutable('2026-08-18T12:00:00+00:00')));

        $route->generate($this->createContext());
    }

    public function testGeneratedTokenDoesNotContainTheContextToken(): void
    {
        $response = $this->route->generate($this->createContext());

        static::assertStringNotContainsString(self::CONTEXT_TOKEN, $response->getHandoffToken());
        static::assertStringNotContainsString(
            self::CONTEXT_TOKEN,
            (string) base64_decode(strtr(explode('.', $response->getHandoffToken())[1], '-_', '+/'), true)
        );
    }

    public function testResponseExposesTheExpiryAsRfc3339(): void
    {
        $route = $this->createRoute(clock: new MockClock(new \DateTimeImmutable('2026-08-18T12:00:00+00:00')));

        static::assertSame('2026-08-18T12:01:00+00:00', $route->generate($this->createContext())->getExpiresAt());
    }

    public function testEachCallMintsAFreshToken(): void
    {
        $first = $this->tokenGenerator->decode($this->route->generate($this->createContext())->getHandoffToken());
        $second = $this->tokenGenerator->decode($this->route->generate($this->createContext())->getHandoffToken());

        static::assertNotSame($first->jti, $second->jti);
    }

    public function testGenerateIsRateLimitedByClientIp(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::CONTEXT_HANDOFF, self::CLIENT_IP);

        $this->createRoute(rateLimiter: $rateLimiter)->generate($this->createContext());
    }

    public function testGenerateThrowsAndDoesNotMintWhenRateLimitExceeded(): void
    {
        $rateLimiter = static::createStub(RateLimiter::class);
        $rateLimiter->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException((new \DateTimeImmutable('+30 seconds'))->getTimestamp()));

        $tokenStore = $this->createMock(ContextHandoffTokenStore::class);
        $tokenStore->expects($this->never())->method('store');

        try {
            $this->createRoute(tokenStore: $tokenStore, rateLimiter: $rateLimiter)->generate($this->createContext());
            static::fail('Expected a throttling exception.');
        } catch (SalesChannelException $exception) {
            static::assertSame(SalesChannelException::CONTEXT_HANDOFF_THROTTLED, $exception->getErrorCode());
        }
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    private function createRoute(
        ?ContextHandoffTokenStore $tokenStore = null,
        ?ClockInterface $clock = null,
        ?RateLimiter $rateLimiter = null,
    ): ContextHandoffGenerateRoute {
        return new ContextHandoffGenerateRoute(
            $this->tokenGenerator,
            $tokenStore ?? $this->tokenStore,
            $clock ?? $this->clock,
            $rateLimiter ?? $this->rateLimiter,
            $this->requestStack,
        );
    }

    private function createContext(): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getToken')->willReturn(self::CONTEXT_TOKEN);
        $context->method('getSalesChannelId')->willReturn($this->salesChannelId);

        return $context;
    }
}
