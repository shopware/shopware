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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Checkout\Customer\RegistrationDoubleSubmitGuard;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
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
        static::assertTrue($cache->getItem(self::markerKey())->get());
    }

    public function testTheSpentTokenIsMarkedWithTheMarkerTtl(): void
    {
        $marker = $this->createMock(CacheItemInterface::class);
        $marker->method('isHit')->willReturn(false);
        $marker->expects($this->once())->method('set')->with(true);
        $marker->expects($this->once())->method('expiresAfter')->with(10);

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

    public function testTheMarkerNeverCarriesTheRotatedToken(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())->guard(
            $context,
            static function () use ($context): void {
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        // a stale context token is exactly what a fixated session presents, so handing back what it
        // became would undo the session migration that login performs
        static::assertTrue($cache->getItem(self::markerKey())->get());
        static::assertSame([self::markerKey()], array_keys($cache->getValues()));

        foreach ($cache->getValues() as $stored) {
            // getValues() hands back serialized payloads, an unserialized comparison never matches
            static::assertStringNotContainsString(self::WINNER_TOKEN, $stored);
        }
    }

    public function testARegistrationThatKeepsItsTokenStillSpendsIt(): void
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
        static::assertSame(self::TOKEN, $context->getToken());
        static::assertTrue($cache->getItem(self::markerKey())->get());
    }

    public function testAConsumedTokenSuppressesTheRegistration(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache);

        $registerCalls = 0;

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(0, $registerCalls);
    }

    public function testEveryResubmissionStaysSuppressedWhileTheMarkerLives(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache);

        $guard = $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger());

        // a suppressed request stays anonymous and keeps presenting the same token, so the marker has
        // to outlive the whole burst rather than one resubmission of it
        for ($resubmission = 0; $resubmission < 3; ++$resubmission) {
            $guard->guard($this->createContext(), static function (): void {
                static::fail('The double submit must not register a second time.');
            });
        }

        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testASuppressedResubmissionDoesNotRenewTheMarker(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        // absent before the lock, written by the competing registration while this request waited
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnOnConsecutiveCalls($this->createMissedMarker(), $this->createHitMarker());
        $cache->expects($this->never())->method('save');
        $cache->expects($this->never())->method('deleteItem');

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())
            ->guard($this->createContext(), static function (): void {
                static::fail('The double submit must not register a second time.');
            });
    }

    public function testARejectedRegistrationDoesNotSpendTheToken(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $guard = $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger());
        $rejection = new \RuntimeException('the submitted data is invalid');

        try {
            $guard->guard($this->createContext(), static function () use ($rejection): void {
                throw $rejection;
            });

            static::fail('The rejection must reach the caller.');
        } catch (\RuntimeException $caught) {
            static::assertSame($rejection, $caught);
        }

        // nothing was created, so the visitor can correct the submission and send it again
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
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
                $this->seedMarker($cache);

                return true;
            });
        $lock->expects($this->once())->method('release');

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())
            ->guard($this->createContext(), static function (): void {
                static::fail('The registration must not run a second time.');
            });

        static::assertTrue($cache->getItem(self::markerKey())->isHit());
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
        // an unguarded run creates a customer too, so it spends the token as well
        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testAFailingAcquireReleasesTheLockAndRegistersUnguarded(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willThrowException(new \RuntimeException('lock store unreachable'));
        // acquire() can throw after the store already saved the lock, it must not be stranded until the TTL expires
        $lock->expects($this->once())->method('release');

        $warnings = [];
        $registerCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            $this->createContext(),
            static function () use (&$registerCalls): void {
                ++$registerCalls;
            }
        );

        static::assertSame(1, $registerCalls);
        static::assertContains('Registration lock could not be acquired, registering unguarded.', $warnings);
        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testAMarkerWrittenWhileTheLockDeadlineExpiresSuppressesTheRegistration(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnOnConsecutiveCalls($this->createMissedMarker(), $this->createHitMarker());
        $cache->expects($this->never())->method('save');

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
        static::assertTrue($cache->getItem(self::markerKey())->isHit());
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

    #[DataProvider('oddMarkerPayloadProvider')]
    public function testAMarkerPayloadIsNeverInterpreted(mixed $storedValue): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem(self::markerKey());
        $item->set($storedValue);
        $cache->save($item);

        // the presence of the marker is the whole signal, a payload left by an older format neither
        // keeps the token usable nor is read back for anything
        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())
            ->guard($this->createContext(), static function (): void {
                static::fail('The registration must not run a second time.');
            });

        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public static function oddMarkerPayloadProvider(): \Generator
    {
        yield 'an empty string' => [''];

        yield 'a null payload, indistinguishable from an unwritten marker for a reader of the value' => [null];

        yield 'a winner token as written by an older marker format' => [self::WINNER_TOKEN];

        yield 'a structured payload from an older marker format' => [['newContextToken' => self::WINNER_TOKEN]];

        yield 'a numeric payload' => [42];
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

    public function testATokenRotatedBeforeTheRegistrationThrewIsStillSpent(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $exception = new \RuntimeException('the confirmation mail could not be sent');

        $guard = $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger());

        try {
            $guard->guard($context, static function () use ($context, $exception): void {
                $context->assign(['token' => self::WINNER_TOKEN]);

                throw $exception;
            });

            static::fail('The failure must reach the caller.');
        } catch (\RuntimeException $caught) {
            static::assertSame($exception, $caught);
        }

        // the customer exists from the rotation on, whatever failed afterwards
        static::assertTrue($cache->getItem(self::markerKey())->get());
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
        static::assertTrue($cache->getItem(self::markerKey())->get());
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
        float $lockWaitTimeout = 0.0,
    ): RegistrationDoubleSubmitGuard {
        return new RegistrationDoubleSubmitGuard($lockFactory, $cache, $logger, $lockWaitTimeout);
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

    private function seedMarker(ArrayAdapter $cache): void
    {
        $item = $cache->getItem(self::markerKey());
        $item->set(true);
        $cache->save($item);
    }

    private function createMissedMarker(): CacheItemInterface&Stub
    {
        $item = static::createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        return $item;
    }

    private function createHitMarker(): CacheItemInterface&Stub
    {
        $item = static::createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(true);

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
