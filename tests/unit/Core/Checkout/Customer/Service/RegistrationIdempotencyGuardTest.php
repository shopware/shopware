<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Checkout\Customer\Service\RegistrationIdempotencyGuard;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegistrationIdempotencyGuard::class)]
class RegistrationIdempotencyGuardTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private const CONTEXT_TOKEN = 'context-token';

    private const REQUEST_DIGEST = 'request-digest';

    public function testRegistrationRunsOnceUnderTheLockAndStoresTheMarker(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $customerId = Uuid::randomHex();
        $response = $this->createCustomerResponse($customerId, 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);

        $marker = $cache->getItem(self::markerKey());
        static::assertTrue($marker->isHit());
        static::assertSame(
            ['customerId' => $customerId, 'newContextToken' => 'new-context-token'],
            $marker->get()
        );
    }

    public function testDuplicateIsRepliedFromTheMarkerWithoutLocking(): void
    {
        $cache = new ArrayAdapter();
        $originalCustomerId = Uuid::randomHex();
        $this->seedMarker($cache, ['customerId' => $originalCustomerId, 'newContextToken' => 'winner-token']);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLock');

        $registerCalls = 0;
        $unexpectedResponse = $this->createCustomerResponse(Uuid::randomHex());
        $register = static function () use (&$registerCalls, $unexpectedResponse): CustomerResponse {
            ++$registerCalls;

            return $unexpectedResponse;
        };

        $replayedResponse = $this->createCustomerResponse($originalCustomerId, 'winner-token');
        $replayArguments = [];
        $replay = static function (string $customerId, ?string $newContextToken) use (&$replayArguments, $replayedResponse): CustomerResponse {
            $replayArguments[] = [$customerId, $newContextToken];

            return $replayedResponse;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, new NullLogger());
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($replayedResponse, $result);
        static::assertSame(0, $registerCalls);
        static::assertSame([[$originalCustomerId, 'winner-token']], $replayArguments);
    }

    public function testDoubleOptInMarkerReplaysWithoutContextToken(): void
    {
        $cache = new ArrayAdapter();
        $originalCustomerId = Uuid::randomHex();
        $this->seedMarker($cache, ['customerId' => $originalCustomerId, 'newContextToken' => null]);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLock');

        $registerCalls = 0;
        $unexpectedResponse = $this->createCustomerResponse(Uuid::randomHex());
        $register = static function () use (&$registerCalls, $unexpectedResponse): CustomerResponse {
            ++$registerCalls;

            return $unexpectedResponse;
        };

        $replayedResponse = $this->createCustomerResponse($originalCustomerId);
        $replayArguments = [];
        $replay = static function (string $customerId, ?string $newContextToken) use (&$replayArguments, $replayedResponse): CustomerResponse {
            $replayArguments[] = [$customerId, $newContextToken];

            return $replayedResponse;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, new NullLogger());
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($replayedResponse, $result);
        static::assertSame(0, $registerCalls);
        static::assertSame([[$originalCustomerId, null]], $replayArguments);
    }

    public function testMarkerWrittenWhileWaitingForTheLockIsReplayedUnderTheLock(): void
    {
        $originalCustomerId = Uuid::randomHex();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnOnConsecutiveCalls(
                $this->createMissItem(),
                $this->createMarkerHitItem($originalCustomerId, 'winner-token'),
            );

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $registerCalls = 0;
        $unexpectedResponse = $this->createCustomerResponse(Uuid::randomHex());
        $register = static function () use (&$registerCalls, $unexpectedResponse): CustomerResponse {
            ++$registerCalls;

            return $unexpectedResponse;
        };

        $replayedResponse = $this->createCustomerResponse($originalCustomerId, 'winner-token');
        $replayArguments = [];
        $replay = static function (string $customerId, ?string $newContextToken) use (&$replayArguments, $replayedResponse): CustomerResponse {
            $replayArguments[] = [$customerId, $newContextToken];

            return $replayedResponse;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, new NullLogger());
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($replayedResponse, $result);
        static::assertSame(0, $registerCalls);
        static::assertSame([[$originalCustomerId, 'winner-token']], $replayArguments);
    }

    public function testMarkerIsScopedToTheSalesChannelWhileTheLockIsTokenGlobal(): void
    {
        $cache = new ArrayAdapter();
        $originalCustomerId = Uuid::randomHex();
        $originalMarker = ['customerId' => $originalCustomerId, 'newContextToken' => 'winner-token'];
        $this->seedMarker($cache, $originalMarker, 'sales-channel-a');

        $lock = $this->createAcquiredLock(2);
        $lock->expects($this->exactly(2))->method('release');
        // the expected lock key is derived from the context token only, both sales channels must use it
        $lockFactory = $this->createLockFactory($lock, 2);

        $customerId = Uuid::randomHex();
        $response = $this->createCustomerResponse($customerId, 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, new NullLogger());
        $guard->guard('sales-channel-b', self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);
        $guard->guard('sales-channel-c', self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame(2, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertSame($originalMarker, $cache->getItem(self::markerKey('sales-channel-a'))->get());
    }

    public function testInvalidReplayFallsBackToAFreshRegistrationAndOverwritesTheMarker(): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache, ['customerId' => Uuid::randomHex(), 'newContextToken' => 'stale-token']);

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $freshCustomerId = Uuid::randomHex();
        $freshResponse = $this->createCustomerResponse($freshCustomerId, 'fresh-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $freshResponse): CustomerResponse {
            ++$registerCalls;

            return $freshResponse;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($freshResponse, $result);
        static::assertSame(1, $registerCalls);
        // once on the fast path and once again under the lock
        static::assertSame(2, $replayCalls);
        static::assertContains(
            'Duplicate registration detected, but the original result is no longer valid. Executing a fresh registration.',
            $warnings
        );
        static::assertSame(
            ['customerId' => $freshCustomerId, 'newContextToken' => 'fresh-token'],
            $cache->getItem(self::markerKey())->get()
        );
    }

    #[DataProvider('malformedMarkerProvider')]
    public function testMalformedMarkerIsTreatedAsMiss(mixed $malformedMarker): void
    {
        $cache = new ArrayAdapter();
        $this->seedMarker($cache, $malformedMarker);

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $freshCustomerId = Uuid::randomHex();
        $freshResponse = $this->createCustomerResponse($freshCustomerId, 'fresh-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $freshResponse): CustomerResponse {
            ++$registerCalls;

            return $freshResponse;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, new NullLogger());
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($freshResponse, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertSame(
            ['customerId' => $freshCustomerId, 'newContextToken' => 'fresh-token'],
            $cache->getItem(self::markerKey())->get()
        );
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function malformedMarkerProvider(): \Generator
    {
        yield 'marker is not an array' => ['not-an-array'];
        yield 'customer id is missing' => [['newContextToken' => 'winner-token']];
        yield 'customer id is not a uuid' => [['customerId' => 'not-a-uuid', 'newContextToken' => 'winner-token']];
        yield 'context token key is missing, a null token would wrongly replay as double-opt-in' => [['customerId' => Uuid::randomHex()]];
        yield 'context token is an empty string' => [['customerId' => Uuid::randomHex(), 'newContextToken' => '']];
        yield 'context token is not a string' => [['customerId' => Uuid::randomHex(), 'newContextToken' => 42]];
    }

    public function testRegistrationExceptionPropagatesWithoutMarkerAndReleasesTheLock(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $exception = new \RuntimeException('registration failed');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $exception): CustomerResponse {
            ++$registerCalls;

            throw $exception;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, new NullLogger());

        try {
            $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);
            static::fail('The registration exception must propagate.');
        } catch (\RuntimeException $caught) {
            static::assertSame($exception, $caught);
        }

        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testReplayExceptionUnderTheLockPropagatesAndReleasesTheLock(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnOnConsecutiveCalls(
                $this->createMissItem(),
                $this->createMarkerHitItem(Uuid::randomHex(), 'winner-token'),
            );

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $exception = new \RuntimeException('replay failed');

        $registerCalls = 0;
        $unexpectedResponse = $this->createCustomerResponse(Uuid::randomHex());
        $register = static function () use (&$registerCalls, $unexpectedResponse): CustomerResponse {
            ++$registerCalls;

            return $unexpectedResponse;
        };

        $replay = static function () use ($exception): ?CustomerResponse {
            throw $exception;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, new NullLogger());

        try {
            $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);
            static::fail('The replay exception must propagate.');
        } catch (\RuntimeException $caught) {
            static::assertSame($exception, $caught);
        }

        static::assertSame(0, $registerCalls);
    }

    public function testUnreadableMarkerOnTheFastPathIsReReadUnderTheLock(): void
    {
        $originalCustomerId = Uuid::randomHex();
        $hitItem = $this->createMarkerHitItem($originalCustomerId, 'winner-token');

        $getItemCalls = 0;
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnCallback(static function () use (&$getItemCalls, $hitItem): CacheItemInterface {
                if (++$getItemCalls === 1) {
                    throw new \RuntimeException('cache read failed');
                }

                return $hitItem;
            });

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $registerCalls = 0;
        $unexpectedResponse = $this->createCustomerResponse(Uuid::randomHex());
        $register = static function () use (&$registerCalls, $unexpectedResponse): CustomerResponse {
            ++$registerCalls;

            return $unexpectedResponse;
        };

        $replayedResponse = $this->createCustomerResponse($originalCustomerId, 'winner-token');
        $replayArguments = [];
        $replay = static function (string $customerId, ?string $newContextToken) use (&$replayArguments, $replayedResponse): CustomerResponse {
            $replayArguments[] = [$customerId, $newContextToken];

            return $replayedResponse;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($replayedResponse, $result);
        static::assertSame(0, $registerCalls);
        static::assertSame([[$originalCustomerId, 'winner-token']], $replayArguments);
        static::assertContains('Registration marker could not be read.', $warnings);
    }

    public function testUnreadableMarkerRunsTheRegistrationUnderTheLock(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new \RuntimeException('cache read failed'));
        $cache->expects($this->never())->method('save');

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $response = $this->createCustomerResponse(Uuid::randomHex(), 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration marker could not be read.', $warnings);
        static::assertContains('Registration marker could not be saved.', $warnings);
    }

    public function testLockCreationFailureRunsTheRegistrationUnguarded(): void
    {
        $cache = new ArrayAdapter();
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->willThrowException(new \RuntimeException('lock backend down'));

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $response = $this->createCustomerResponse(Uuid::randomHex(), 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration lock could not be created, executing the registration unguarded.', $warnings);
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testLockAcquireExceptionReleasesTheLockAndRunsTheRegistrationUnguarded(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willThrowException(new \RuntimeException('lock store failed'));
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $response = $this->createCustomerResponse(Uuid::randomHex(), 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration lock could not be acquired, executing the registration unguarded.', $warnings);
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testUnacquiredLockRunsTheRegistrationUnguardedAfterTheWaitDeadline(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);
        $lock->expects($this->never())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $response = $this->createCustomerResponse(Uuid::randomHex(), 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        // an already exhausted deadline keeps the test free of retry sleeps
        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger, lockWaitTimeout: 0.0);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration lock was not acquired within the wait deadline, executing the registration unguarded.', $warnings);
        static::assertFalse($cache->getItem(self::markerKey())->isHit());
    }

    public function testWinnerMarkerWrittenDuringTheWaitDeadlineIsReplayedAfterTimeout(): void
    {
        $originalCustomerId = Uuid::randomHex();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::markerKey())
            ->willReturnOnConsecutiveCalls(
                $this->createMissItem(),
                $this->createMarkerHitItem($originalCustomerId, 'winner-token'),
            );

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);
        $lock->expects($this->never())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $registerCalls = 0;
        $unexpectedResponse = $this->createCustomerResponse(Uuid::randomHex());
        $register = static function () use (&$registerCalls, $unexpectedResponse): CustomerResponse {
            ++$registerCalls;

            return $unexpectedResponse;
        };

        $replayedResponse = $this->createCustomerResponse($originalCustomerId, 'winner-token');
        $replayArguments = [];
        $replay = static function (string $customerId, ?string $newContextToken) use (&$replayArguments, $replayedResponse): CustomerResponse {
            $replayArguments[] = [$customerId, $newContextToken];

            return $replayedResponse;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger, lockWaitTimeout: 0.0);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($replayedResponse, $result);
        static::assertSame(0, $registerCalls);
        static::assertSame([[$originalCustomerId, 'winner-token']], $replayArguments);
        static::assertSame([], $warnings);
    }

    public function testContendedLockIsRetriedAndAcquiredWithinTheWaitDeadline(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly(2))
            ->method('acquire')
            ->with(false)
            ->willReturnOnConsecutiveCalls(false, true);
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $customerId = Uuid::randomHex();
        $response = $this->createCustomerResponse($customerId, 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger, lockWaitTimeout: 5.0);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertSame(
            ['customerId' => $customerId, 'newContextToken' => 'new-context-token'],
            $cache->getItem(self::markerKey())->get()
        );
    }

    public function testMarkerSaveReturningFalseDoesNotChangeTheResponse(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with(self::markerKey())->willReturn($this->createMissItem());
        $cache->expects($this->once())->method('save')->willReturn(false);

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $response = $this->createCustomerResponse(Uuid::randomHex(), 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration marker could not be saved.', $warnings);
    }

    public function testMarkerSaveExceptionDoesNotChangeTheResponse(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with(self::markerKey())->willReturn($this->createMissItem());
        $cache->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('cache write failed'));

        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())->method('release');
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $response = $this->createCustomerResponse(Uuid::randomHex(), 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration marker could not be saved.', $warnings);
    }

    public function testReleaseExceptionAfterSuccessDoesNotChangeTheResponse(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())
            ->method('release')
            ->willThrowException(new \RuntimeException('release failed'));
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $customerId = Uuid::randomHex();
        $response = $this->createCustomerResponse($customerId, 'new-context-token');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $response): CustomerResponse {
            ++$registerCalls;

            return $response;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);
        $result = $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);

        static::assertSame($response, $result);
        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration lock could not be released.', $warnings);
        static::assertSame(
            ['customerId' => $customerId, 'newContextToken' => 'new-context-token'],
            $cache->getItem(self::markerKey())->get()
        );
    }

    public function testReleaseExceptionDoesNotMaskTheRegistrationException(): void
    {
        $cache = new ArrayAdapter();
        $lock = $this->createAcquiredLock();
        $lock->expects($this->once())
            ->method('release')
            ->willThrowException(new \LogicException('release failed'));
        $lockFactory = $this->createLockFactory($lock);

        $warnings = [];
        $logger = $this->createWarningCollector($warnings);

        $exception = new \RuntimeException('registration failed');

        $registerCalls = 0;
        $register = static function () use (&$registerCalls, $exception): CustomerResponse {
            ++$registerCalls;

            throw $exception;
        };

        $replayCalls = 0;
        $replay = static function () use (&$replayCalls): ?CustomerResponse {
            ++$replayCalls;

            return null;
        };

        $guard = new RegistrationIdempotencyGuard($lockFactory, $cache, $logger);

        try {
            $guard->guard(self::SALES_CHANNEL_ID, self::CONTEXT_TOKEN, self::REQUEST_DIGEST, $register, $replay);
            static::fail('The registration exception must propagate.');
        } catch (\RuntimeException $caught) {
            static::assertSame($exception, $caught);
        }

        static::assertSame(1, $registerCalls);
        static::assertSame(0, $replayCalls);
        static::assertContains('Registration lock could not be released.', $warnings);
    }

    private static function lockKey(): string
    {
        return 'customer-registration-lock-' . hash('sha256', self::CONTEXT_TOKEN);
    }

    private static function markerKey(string $salesChannelId = self::SALES_CHANNEL_ID): string
    {
        return 'customer-registration-' . hash('sha256', $salesChannelId . '|' . self::CONTEXT_TOKEN . '|' . self::REQUEST_DIGEST);
    }

    private function seedMarker(ArrayAdapter $cache, mixed $marker, string $salesChannelId = self::SALES_CHANNEL_ID): void
    {
        $item = $cache->getItem(self::markerKey($salesChannelId));
        $item->set($marker);
        $cache->save($item);
    }

    private function createCustomerResponse(string $customerId, ?string $contextToken = null): CustomerResponse
    {
        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setUniqueIdentifier($customerId);

        $response = new CustomerResponse($customer);

        if ($contextToken !== null) {
            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        }

        return $response;
    }

    private function createLockFactory(SharedLockInterface $lock, int $expectedCreations = 1): LockFactory&MockObject
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->exactly($expectedCreations))
            ->method('createLock')
            ->with(self::lockKey(), 30.0, false)
            ->willReturn($lock);

        return $lockFactory;
    }

    private function createAcquiredLock(int $expectedAcquisitions = 1): SharedLockInterface&MockObject
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly($expectedAcquisitions))
            ->method('acquire')
            ->with(false)
            ->willReturn(true);

        return $lock;
    }

    private function createMissItem(): CacheItemInterface&Stub
    {
        $item = static::createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        return $item;
    }

    private function createMarkerHitItem(string $customerId, ?string $newContextToken): CacheItemInterface&Stub
    {
        $item = static::createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(['customerId' => $customerId, 'newContextToken' => $newContextToken]);

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
