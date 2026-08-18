<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenGenerator;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextHandoffGenerateRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextHandoffGenerateRoute::class)]
class ContextHandoffGenerateRouteTest extends TestCase
{
    private const CONTEXT_TOKEN = 'the-current-context-token';

    private string $salesChannelId;

    private ContextHandoffTokenGenerator $tokenGenerator;

    private ContextHandoffTokenStore&Stub $tokenStore;

    private MockClock $clock;

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

        $this->route = new ContextHandoffGenerateRoute($this->tokenGenerator, $this->tokenStore, $this->clock);
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

        $route = new ContextHandoffGenerateRoute($this->tokenGenerator, $tokenStore, $this->clock);

        $handoffToken = $this->tokenGenerator->decode($route->generate($this->createContext())->getHandoffToken());

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

        $route = new ContextHandoffGenerateRoute(
            $this->tokenGenerator,
            $tokenStore,
            new MockClock(new \DateTimeImmutable('2026-08-18T12:00:00+00:00'))
        );

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
        $route = new ContextHandoffGenerateRoute(
            $this->tokenGenerator,
            $this->tokenStore,
            new MockClock(new \DateTimeImmutable('2026-08-18T12:00:00+00:00'))
        );

        static::assertSame('2026-08-18T12:01:00+00:00', $route->generate($this->createContext())->getExpiresAt());
    }

    public function testEachCallMintsAFreshToken(): void
    {
        $first = $this->tokenGenerator->decode($this->route->generate($this->createContext())->getHandoffToken());
        $second = $this->tokenGenerator->decode($this->route->generate($this->createContext())->getHandoffToken());

        static::assertNotSame($first->jti, $second->jti);
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    private function createContext(): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getToken')->willReturn(self::CONTEXT_TOKEN);
        $context->method('getSalesChannelId')->willReturn($this->salesChannelId);

        return $context;
    }
}
