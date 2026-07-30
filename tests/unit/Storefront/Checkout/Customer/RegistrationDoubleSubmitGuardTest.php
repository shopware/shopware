<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Checkout\Customer\RegistrationDoubleSubmitGuard;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegistrationDoubleSubmitGuard::class)]
class RegistrationDoubleSubmitGuardTest extends TestCase
{
    private const TOKEN = 'context-token';

    private const WINNER_TOKEN = 'winner-context-token';

    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private const CHANNEL_TOKEN_KEY = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . self::SALES_CHANNEL_ID;

    private Session $session;

    private Request $mainRequest;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();

        $this->mainRequest = new Request(attributes: [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => self::SALES_CHANNEL_ID]);
        $this->mainRequest->setSession($this->session);

        $this->requestStack = new RequestStack([$this->mainRequest]);
    }

    public function testFirstSubmissionRegistersUnderTheLock(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $context = $this->createContext();
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $logger)->guard(
            $context,
            static function () use (&$registerCalls, $context): void {
                ++$registerCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertSame(self::WINNER_TOKEN, $cache->getItem(self::markerKey())->get());
    }

    public function testTheRotatedTokenIsStoredAsTheMarkerWithTheMarkerTtl(): void
    {
        $marker = $this->createMock(CacheItemInterface::class);
        $marker->method('isHit')->willReturn(false);
        $marker->expects($this->once())->method('set')->with(self::WINNER_TOKEN);
        $marker->expects($this->once())->method('expiresAfter')->with(30);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with(self::markerKey())->willReturn($marker);
        $cache->expects($this->once())->method('save')->with($marker)->willReturn(true);

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())->guard(
            $context,
            static function () use ($context): void {
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(self::WINNER_TOKEN, $context->getToken());
    }

    public function testADoubleOptInRegistrationKeepsItsTokenUsable(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $registerCalls = 0;

        // a double opt-in registration leaves the visitor anonymous, the token is not rotated
        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())->guard(
            $context,
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testAConsumedTokenSuppressesTheRegistrationAndAdoptsTheWinnerToken(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache, self::WINNER_TOKEN);

        $registerCalls = 0;

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(0, $registerCalls);
        static::assertSame(self::WINNER_TOKEN, $this->session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(self::WINNER_TOKEN, $this->mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAMarkerWrittenWhileWaitingForTheLockStillSuppressesTheRegistration(): void
    {
        $cache = new ArrayAdapter();

        // the competing registration finishes while this request waits for the lock, so the marker
        // is absent before the lock and present after it
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->willReturnCallback(function () use ($cache): bool {
                $this->seedMarker($cache, self::WINNER_TOKEN);

                return true;
            });
        $lock->expects($this->once())->method('release');

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())
            ->guard($this->createContext(), static function (): void {
                static::fail('The registration must not run a second time.');
            });

        static::assertSame(self::WINNER_TOKEN, $this->session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAdoptingTheWinnerTokenMigratesTheSessionAndWritesTheDefaultTokenKeyOnly(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache, self::WINNER_TOKEN);

        $anonymousSessionId = $this->session->getId();

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())
            ->guard($this->createContext(), static function (): void {
                static::fail('The registration must not run again.');
            });

        static::assertNotSame($anonymousSessionId, $this->session->getId());
        static::assertSame($this->session->getId(), $this->session->get('sessionId'));
        static::assertSame(self::WINNER_TOKEN, $this->session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($this->session->has(self::CHANNEL_TOKEN_KEY));
    }

    public function testAdoptingTheWinnerTokenAlsoWritesTheSalesChannelBoundTokenKeyWhenCustomerBindingIsEnabled(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache, self::WINNER_TOKEN);

        $anonymousSessionId = $this->session->getId();

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger(), customerBoundToSalesChannel: true)
            ->guard($this->createContext(), static function (): void {
                static::fail('The registration must not run again.');
            });

        static::assertNotSame($anonymousSessionId, $this->session->getId());
        static::assertSame(self::WINNER_TOKEN, $this->session->get(self::CHANNEL_TOKEN_KEY));
        static::assertSame(self::WINNER_TOKEN, $this->session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testSuppressionWithoutAMainRequestLeavesTheSessionAlone(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache, self::WINNER_TOKEN);

        $this->requestStack = new RequestStack();

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())
            ->guard($this->createContext(), static function (): void {
                static::fail('The registration must not run again.');
            });

        static::assertFalse($this->session->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testSuppressionWithAnUninitializedSessionLeavesTheSessionAlone(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache, self::WINNER_TOKEN);

        $this->mainRequest = new Request(attributes: [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => self::SALES_CHANNEL_ID]);
        $this->mainRequest->setSessionFactory(fn (): Session => $this->session);
        $this->requestStack = new RequestStack([$this->mainRequest]);

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())
            ->guard($this->createContext(), static function (): void {
                static::fail('The registration must not run again.');
            });

        static::assertFalse($this->session->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNull($this->mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAnUncreatableLockRegistersUnguarded(): void
    {
        $cache = new ArrayAdapter();

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->willThrowException(new \RuntimeException('lock store unreachable'));

        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($lockFactory, $cache, $this->createWarningCollector($warnings))->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration lock could not be created, registering unguarded.', $warnings);
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testAFailingAcquireReleasesTheLockAndRegistersUnguarded(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willThrowException(new \RuntimeException('lock store unreachable'));
        // acquire() can throw after the store already saved the lock, it must not be stranded until the TTL expires
        $lock->expects($this->once())->method('release');

        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), new ArrayAdapter(), $this->createWarningCollector($warnings))->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration lock could not be acquired, registering unguarded.', $warnings);
    }

    public function testAMarkerWrittenWhileWaitingForTheLockSuppressesTheRegistration(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnOnConsecutiveCalls($this->createMissedMarker(), $this->createMarker(self::WINNER_TOKEN));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(0, $registerCalls);
        static::assertSame(self::WINNER_TOKEN, $this->session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAMissedLockDeadlineWithoutAMarkerRegistersUnguarded(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration lock was not acquired within the wait deadline, registering unguarded.', $warnings);
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testTheLockIsRetriedUntilTheWaitDeadline(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly(2))->method('acquire')->willReturnOnConsecutiveCalls(false, true);
        $lock->expects($this->once())->method('release');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $registerCalls = 0;

        // one retry fits into the budget: 0.05s / 50ms retry delay
        $this->createGuard($this->createLockFactory($lock), new ArrayAdapter(), $logger, lockWaitTimeout: 0.05)->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(1, $registerCalls);
    }

    public function testAnUnreadableMarkerStillRegistersUnderTheLock(): void
    {
        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new \RuntimeException('cache backend down'));

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            $context,
            static function () use (&$registerCalls, $context): void {
                ++$registerCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration marker could not be read.', $warnings);
        static::assertContains('Registration marker could not be saved.', $warnings);
    }

    public function testAnUnsavedMarkerIsLoggedWithoutFailingTheRegistration(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createMissedMarker());
        $cache->expects($this->once())->method('save')->willReturn(false);

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            $context,
            static function () use (&$registerCalls, $context): void {
                ++$registerCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration marker could not be saved.', $warnings);
    }

    public function testAThrowingMarkerSaveIsLoggedWithoutFailingTheRegistration(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createMissedMarker());
        $cache->expects($this->once())->method('save')->willThrowException(new \RuntimeException('cache write failed'));

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            $context,
            static function () use (&$registerCalls, $context): void {
                ++$registerCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration marker could not be saved.', $warnings);
    }

    #[DataProvider('corruptMarkerProvider')]
    public function testACorruptMarkerIsTreatedAsAnUnconsumedToken(mixed $storedValue): void
    {
        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createMarker($storedValue));

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(1, $registerCalls);
        // a value that cannot be a context token must never be adopted as one
        static::assertFalse($this->session->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public static function corruptMarkerProvider(): \Generator
    {
        yield 'an empty string cannot address a context' => [''];

        yield 'a null payload is indistinguishable from an unwritten marker' => [null];

        yield 'a structured payload from an older marker format' => [['newContextToken' => self::WINNER_TOKEN]];

        yield 'a numeric payload cannot address a context' => [42];
    }

    public function testTheLockIsReleasedWhenTheRegistrationThrows(): void
    {
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $exception = new \RuntimeException('registration failed');

        $guard = $this->createGuard($this->createLockFactory($lock), new ArrayAdapter(), new NullLogger());

        $this->expectExceptionObject($exception);

        $guard->guard($this->createContext(), static function () use ($exception): void {
            throw $exception;
        });
    }

    public function testAFailingReleaseAfterASuccessfulRegistrationIsLoggedOnly(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release')->willThrowException(new \RuntimeException('release failed'));

        $context = $this->createContext();
        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            $context,
            static function () use (&$registerCalls, $context): void {
                ++$registerCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration lock could not be released.', $warnings);
        static::assertSame(self::WINNER_TOKEN, $cache->getItem(self::markerKey())->get());
    }

    private static function markerKey(): string
    {
        return 'storefront-registration-' . hash('sha256', self::TOKEN);
    }

    private static function lockKey(): string
    {
        return 'storefront-registration-lock-' . hash('sha256', self::TOKEN);
    }

    private function createContext(): SalesChannelContext
    {
        return Generator::generateSalesChannelContext(token: self::TOKEN);
    }

    private function createGuard(
        LockFactory $lockFactory,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        bool $customerBoundToSalesChannel = false,
        float $lockWaitTimeout = 0.0,
    ): RegistrationDoubleSubmitGuard {
        return new RegistrationDoubleSubmitGuard(
            $lockFactory,
            $cache,
            $logger,
            $this->requestStack,
            new StaticSystemConfigService([
                'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => $customerBoundToSalesChannel,
            ]),
            $lockWaitTimeout,
        );
    }

    private function createLockFactory(SharedLockInterface $lock): LockFactory
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with(self::lockKey(), 30.0, false)
            ->willReturn($lock);

        return $lockFactory;
    }

    private function createUnusedLockFactory(): LockFactory
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLock');

        return $lockFactory;
    }

    private function createAcquiredLock(): SharedLockInterface&MockObject
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);

        return $lock;
    }

    private function seedMarker(ArrayAdapter $cache, string $winnerToken): void
    {
        $item = $cache->getItem(self::markerKey());
        $item->set($winnerToken);
        $cache->save($item);
    }

    private function createMissedMarker(): CacheItemInterface&Stub
    {
        $item = static::createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        return $item;
    }

    private function createMarker(mixed $storedValue): CacheItemInterface&Stub
    {
        $item = static::createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($storedValue);

        return $item;
    }

    /**
     * @param list<string> $warnings
     */
    private function createWarningCollector(array &$warnings): LoggerInterface
    {
        $logger = static::createStub(LoggerInterface::class);
        $logger->method('warning')
            ->willReturnCallback(static function (string|\Stringable $message) use (&$warnings): void {
                $warnings[] = (string) $message;
            });

        return $logger;
    }
}
