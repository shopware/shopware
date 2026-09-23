<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Guard;

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
use Shopware\Storefront\Framework\Guard\DoubleSubmitGuard;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DoubleSubmitGuard::class)]
class DoubleSubmitGuardTest extends TestCase
{
    private const SCOPE = 'storefront-registration';

    private const TOKEN = 'context-token';

    private const WINNER_TOKEN = 'winner-context-token';

    public function testFirstSubmissionRunsUnderTheLock(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $context = $this->createContext();
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $logger)->guard(
            self::SCOPE,
            $context,
            static function () use (&$submitCalls, $context): void {
                ++$submitCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $submitCalls);
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
            self::SCOPE,
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
            self::SCOPE,
            $context,
            static function () use ($context): void {
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertTrue($cache->getItem(self::markerKey())->get());
        static::assertSame([self::markerKey()], array_keys($cache->getValues()));

        foreach ($cache->getValues() as $stored) {
            static::assertStringNotContainsString(self::WINNER_TOKEN, $stored);
        }
    }

    public function testASubmissionThatKeepsItsTokenStillSpendsIt(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())->guard(
            self::SCOPE,
            $context,
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertSame(self::TOKEN, $context->getToken());
        static::assertTrue($cache->getItem(self::markerKey())->get());
    }

    public function testAConsumedTokenSuppressesTheSubmission(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache);

        $submitCalls = 0;

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())->guard(
            self::SCOPE,
            $this->createContext(),
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertSame(0, $submitCalls);
    }

    public function testAScopeOnlySpendsItsOwnToken(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache);

        $otherScope = 'storefront-contact-form';

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with($otherScope . '-lock-' . self::TOKEN, 30.0, false)
            ->willReturn($lock);

        $submitCalls = 0;

        $this->createGuard($lockFactory, $cache, new NullLogger())->guard(
            $otherScope,
            $this->createContext(),
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertTrue($cache->getItem($otherScope . '-' . self::TOKEN)->isHit());
    }

    public function testTheGuardReportsWhetherTheSubmissionRan(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $submitted = $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())
            ->guard(self::SCOPE, $this->createContext(), static function (): void {
            });

        static::assertTrue($submitted);

        $suppressed = $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())
            ->guard(self::SCOPE, $this->createContext(), static function (): void {
                static::fail('The double submit must not run a second time.');
            });

        static::assertFalse($suppressed);
    }

    public function testEveryResubmissionStaysSuppressedWhileTheMarkerLives(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache);

        $guard = $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger());

        for ($resubmission = 0; $resubmission < 3; ++$resubmission) {
            $guard->guard(self::SCOPE, $this->createContext(), static function (): void {
                static::fail('The double submit must not run a second time.');
            });
        }

        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testASuppressedResubmissionDoesNotRenewTheMarker(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnOnConsecutiveCalls($this->createMissedMarker(), $this->createHitMarker());
        $cache->expects($this->never())->method('save');
        $cache->expects($this->never())->method('deleteItem');

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())
            ->guard(self::SCOPE, $this->createContext(), static function (): void {
                static::fail('The double submit must not run a second time.');
            });
    }

    public function testARejectedSubmissionDoesNotSpendTheToken(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $guard = $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger());
        $rejection = new \RuntimeException('the submitted data is invalid');

        try {
            $guard->guard(self::SCOPE, $this->createContext(), static function () use ($rejection): void {
                throw $rejection;
            });

            static::fail('The rejection must reach the caller.');
        } catch (\RuntimeException $caught) {
            static::assertSame($rejection, $caught);
        }

        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testAMarkerWrittenWhileWaitingForTheLockStillSuppressesTheSubmission(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->willReturnCallback(function () use ($cache): bool {
                $this->seedMarker($cache);

                return true;
            });
        $lock->expects($this->once())->method('release');

        $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger())
            ->guard(self::SCOPE, $this->createContext(), static function (): void {
                static::fail('The double submit must not run a second time.');
            });

        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testAnUncreatableLockSubmitsUnguarded(): void
    {
        $cache = new ArrayAdapter();

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->willThrowException(new \RuntimeException('lock store unreachable'));

        $warnings = [];
        $submitCalls = 0;

        $submitted = $this->createGuard($lockFactory, $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $this->createContext(),
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertTrue($submitted, 'a guard that fails open must report the submission as run');
        static::assertSame(1, $submitCalls);
        static::assertContains('Double submit lock could not be created, submitting unguarded.', $warnings);
        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testAFailingAcquireReleasesTheLockAndSubmitsUnguarded(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willThrowException(new \RuntimeException('lock store unreachable'));
        $lock->expects($this->once())->method('release');

        $warnings = [];
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $this->createContext(),
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertContains('Double submit lock could not be acquired, submitting unguarded.', $warnings);
        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testAMarkerWrittenWhileTheLockDeadlineExpiresSuppressesTheSubmission(): void
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

        $warnings = [];
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $this->createContext(),
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertSame(0, $submitCalls);
        static::assertSame([], $warnings, 'a suppressed submission is not a degradation, so it must not warn');
    }

    public function testAMissedLockDeadlineWithoutAMarkerSubmitsUnguarded(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $warnings = [];
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $this->createContext(),
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertContains('Double submit lock was not acquired within the wait deadline, submitting unguarded.', $warnings);
        static::assertTrue($cache->getItem(self::markerKey())->isHit());
    }

    public function testTheLockIsRetriedUntilTheWaitDeadline(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly(2))->method('acquire')->willReturnOnConsecutiveCalls(false, true);
        $lock->expects($this->once())->method('release');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), new ArrayAdapter(), $logger, lockWaitTimeout: 0.05)->guard(
            self::SCOPE,
            $this->createContext(),
            static function () use (&$submitCalls): void {
                ++$submitCalls;
            }
        );

        static::assertSame(1, $submitCalls);
    }

    public function testAnUnreadableMarkerStillRunsUnderTheLock(): void
    {
        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new \RuntimeException('cache backend down'));

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $warnings = [];
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $context,
            static function () use (&$submitCalls, $context): void {
                ++$submitCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertContains('Double submit marker could not be read.', $warnings);
        static::assertContains('Double submit marker could not be saved.', $warnings);
    }

    public function testAnUnsavedMarkerIsLoggedWithoutFailingTheSubmission(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createMissedMarker());
        $cache->expects($this->once())->method('save')->willReturn(false);

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $warnings = [];
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $context,
            static function () use (&$submitCalls, $context): void {
                ++$submitCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertContains('Double submit marker could not be saved.', $warnings);
    }

    public function testAThrowingMarkerSaveIsLoggedWithoutFailingTheSubmission(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createMissedMarker());
        $cache->expects($this->once())->method('save')->willThrowException(new \RuntimeException('cache write failed'));

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $warnings = [];
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $context,
            static function () use (&$submitCalls, $context): void {
                ++$submitCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertContains('Double submit marker could not be saved.', $warnings);
    }

    #[DataProvider('oddMarkerPayloadProvider')]
    public function testAMarkerPayloadIsNeverInterpreted(mixed $storedValue): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem(self::markerKey());
        $item->set($storedValue);
        $cache->save($item);

        $this->createGuard($this->createUnusedLockFactory(), $cache, new NullLogger())
            ->guard(self::SCOPE, $this->createContext(), static function (): void {
                static::fail('The double submit must not run a second time.');
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

    public function testTheLockIsReleasedWhenTheSubmissionThrows(): void
    {
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $exception = new \RuntimeException('the submission failed');

        $guard = $this->createGuard($this->createLockFactory($lock), new ArrayAdapter(), new NullLogger());

        $this->expectExceptionObject($exception);

        $guard->guard(self::SCOPE, $this->createContext(), static function () use ($exception): void {
            throw $exception;
        });
    }

    public function testATokenRotatedBeforeTheSubmissionThrewIsStillSpent(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');

        $context = $this->createContext();
        $exception = new \RuntimeException('the confirmation mail could not be sent');

        $guard = $this->createGuard($this->createLockFactory($lock), $cache, new NullLogger());

        try {
            $guard->guard(self::SCOPE, $context, static function () use ($context, $exception): void {
                $context->assign(['token' => self::WINNER_TOKEN]);

                throw $exception;
            });

            static::fail('The failure must reach the caller.');
        } catch (\RuntimeException $caught) {
            static::assertSame($exception, $caught);
        }

        static::assertTrue($cache->getItem(self::markerKey())->get());
    }

    public function testAFailingReleaseAfterASuccessfulSubmissionIsLoggedOnly(): void
    {
        $cache = new ArrayAdapter();

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release')->willThrowException(new \RuntimeException('release failed'));

        $context = $this->createContext();
        $warnings = [];
        $submitCalls = 0;

        $this->createGuard($this->createLockFactory($lock), $cache, $this->createWarningCollector($warnings))->guard(
            self::SCOPE,
            $context,
            static function () use (&$submitCalls, $context): void {
                ++$submitCalls;
                $context->assign(['token' => self::WINNER_TOKEN]);
            }
        );

        static::assertSame(1, $submitCalls);
        static::assertContains('Double submit lock could not be released.', $warnings);
        static::assertTrue($cache->getItem(self::markerKey())->get());
    }

    private static function markerKey(): string
    {
        return self::SCOPE . '-' . self::TOKEN;
    }

    private static function lockKey(): string
    {
        return self::SCOPE . '-lock-' . self::TOKEN;
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
    ): DoubleSubmitGuard {
        return new DoubleSubmitGuard($lockFactory, $cache, $logger, $lockWaitTimeout);
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
