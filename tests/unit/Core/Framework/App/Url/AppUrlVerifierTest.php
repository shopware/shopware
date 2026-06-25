<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Url;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationState;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[CoversClass(AppUrlVerifier::class)]
class AppUrlVerifierTest extends TestCase
{
    public function testVerifyReturnsTrueInDevMode(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 204]));
        $lockFactory = new LockFactory(new InMemoryStore());

        $shopId = ShopId::v2('shop-id');

        $verifier = new AppUrlVerifier('dev', '6.7.1.0', $cache, $http, $lockFactory, $this->createMock(LoggerInterface::class), $clock);
        static::assertTrue($verifier->verify($shopId));
    }

    public function testVerifyReturnsFalseIfnNoAppUrl(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 204]));
        $lockFactory = new LockFactory(new InMemoryStore());

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $lockFactory, $this->createMock(LoggerInterface::class), $clock);

        $shopId = ShopId::v2('shop-id');
        $result = $verifier->verify($shopId);

        static::assertFalse($result);
    }

    public function testVerifyReturnsTrueIfLockCannotBeAcquired(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 204]));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $lockFactory, $this->createMock(LoggerInterface::class), $clock);

        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);
        static::assertTrue($verifier->verify($shopId));
    }

    public function testVerifyReturnsTrueIfCreateLockThrowsException(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 204]));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')
            ->willThrowException(new LockConflictedException('cannot acquire'));

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $lockFactory, $this->createMock(LoggerInterface::class), $clock);

        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);
        static::assertTrue($verifier->verify($shopId));
    }

    #[DataProvider('verifyOutcomeProvider')]
    public function testVerifyOutcomes(
        MockResponse|callable $responseFactory,
        string $url,
        bool $expectedResult,
        VerificationStatus $expectedStatus,
        ?string $expectedInfo
    ): void {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $locks = new LockFactory(new InMemoryStore());

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, new MockHttpClient($responseFactory), $locks, $this->createMock(LoggerInterface::class), $clock);
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => $url]);

        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();

        static::assertSame($expectedResult, $result);
        self::assertState(['status' => $expectedStatus, 'info' => $expectedInfo], $state);
    }

    /**
     * @return iterable<string, array{MockResponse|callable, string, bool, VerificationStatus, string|null}>
     */
    public static function verifyOutcomeProvider(): iterable
    {
        yield '204 > PASS' => [
            new MockResponse('', ['http_code' => 204]),
            'https://example.com',
            true,
            VerificationStatus::PASS,
            null,
        ];

        yield '500 > SOFT_FAIL' => [
            new MockResponse('internal error', ['http_code' => 500]),
            'https://example.com',
            true,
            VerificationStatus::SOFT_FAIL,
            'Unexpected response from APP_URL verification endpoint: HTTP code: "500" body: "internal error"',
        ];

        yield 'TransportException > SOFT_FAIL' => [
            fn () => throw new TransportException('network down'),
            'https://example.com',
            true,
            VerificationStatus::SOFT_FAIL,
            'Failed to connect to APP_URL: network down',
        ];

        yield 'Generic exception > HARD_FAIL' => [
            fn () => throw new \RuntimeException('boom'),
            'https://example.com',
            false,
            VerificationStatus::HARD_FAIL,
            'boom',
        ];

        yield '429 > SOFT_FAIL' => [
            new MockResponse('too many requests', ['http_code' => 429]),
            'https://example.com',
            true,
            VerificationStatus::SOFT_FAIL,
            'Unexpected response from APP_URL verification endpoint: HTTP code: "429" body: "too many requests"',
        ];

        yield '404 > HARD_FAIL' => [
            new MockResponse('not found', ['http_code' => 404]),
            'https://example.com',
            false,
            VerificationStatus::HARD_FAIL,
            'Unexpected response from APP_URL verification endpoint: HTTP code: "404" body: "not found"',
        ];

        yield 'invalid URL > HARD_FAIL' => [
            new MockResponse('', ['http_code' => 204]),
            'not-a-url',
            false,
            VerificationStatus::HARD_FAIL,
            'APP_URL is invalid: Invalid URL format.',
        ];

        yield '200 (non-204) > HARD_FAIL' => [
            new MockResponse('ok', ['http_code' => 200]),
            'https://example.com',
            false,
            VerificationStatus::HARD_FAIL,
            'Unexpected response from APP_URL verification endpoint: HTTP code: "200" body: "ok"',
        ];

        yield '302 > HARD_FAIL' => [
            new MockResponse('', ['http_code' => 302]),
            'https://example.com',
            false,
            VerificationStatus::HARD_FAIL,
            'Unexpected response from APP_URL verification endpoint: HTTP code: "302" body: ""',
        ];
    }

    /**
     * @param list<int> $httpResponseCodes
     * @param list<array{sleep: int, status: VerificationStatus, tries: int, httpCalls: int, return: bool}> $steps
     */
    #[DataProvider('backoffScenarioProvider')]
    public function testBackoffAndRetryScenario(array $httpResponseCodes, array $steps, VerificationStatus $expectedFinal): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2025-01-01T00:00:00Z'));
        $cache = new ArrayAdapter(clock: $clock);
        $locks = new LockFactory(new InMemoryStore());

        $http = new MockHttpClient(
            array_map(
                fn (int $code): MockResponse => new MockResponse($code === 204 ? '' : 'server error', ['http_code' => $code]),
                $httpResponseCodes
            )
        );

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        foreach ($steps as $step) {
            if ($step['sleep'] > 0) {
                $clock->sleep($step['sleep']);
            }

            $result = $verifier->verify($shopId);
            $state = $verifier->getCurrentState();

            $this->assertStep($state, $step, $result, $http->getRequestsCount());
        }

        $finalState = $verifier->getCurrentState();
        static::assertNotNull($finalState);
        static::assertSame($expectedFinal, $finalState->status);
        if ($expectedFinal === VerificationStatus::HARD_FAIL) {
            $item = $cache->getItem(AppUrlVerifier::VERIFICATION_RESULT_CACHE_KEY);
            static::assertTrue($item->isHit());

            $clock->sleep(60 * 60 * 24); // sleep 24 hours and check again, hard fail should not expire
            $item2 = $cache->getItem(AppUrlVerifier::VERIFICATION_RESULT_CACHE_KEY);
            static::assertTrue($item2->isHit());
        }
    }

    /**
     * @return iterable<string, array{0: list<int>, 1: list<array{sleep: int, status: VerificationStatus, tries: int, httpCalls: int, return: bool}>, 2: VerificationStatus}>
     */
    public static function backoffScenarioProvider(): iterable
    {
        yield '500 > 500 > 500 (soft fail retries with exponential backoff)' => [
            [500, 500, 500],
            [
                self::step(0, VerificationStatus::SOFT_FAIL, 1, 1, true),  // first attempt
                self::step(60 - 1, VerificationStatus::SOFT_FAIL, 1, 1, true), // inside backoff1 (no retry)
                self::step(1, VerificationStatus::SOFT_FAIL, 2, 2, true), // on boundary1 (second attempt)
                self::step(120 - 1, VerificationStatus::SOFT_FAIL, 2, 2, true), // inside backoff2 (no retry)
                self::step(1, VerificationStatus::SOFT_FAIL, 3, 3, true), // on boundary2 (third attempt)
            ],
            VerificationStatus::SOFT_FAIL,
        ];
        yield '500 > 500 > 204 (soft fail is converted to pass on retry)' => [
            [500, 500, 204],
            [
                self::step(0, VerificationStatus::SOFT_FAIL, 1, 1, true), // first attempt
                self::step(60 - 1, VerificationStatus::SOFT_FAIL, 1, 1, true), // inside backoff1 (no retry)
                self::step(1, VerificationStatus::SOFT_FAIL, 2, 2, true), // on boundary1 (second attempt)
                self::step(120 - 1, VerificationStatus::SOFT_FAIL, 2, 2, true), // inside backoff2 (no retry)
                self::step(1, VerificationStatus::PASS, 3, 3, true), // on boundary2 (final attempt)
            ],
            VerificationStatus::PASS,
        ];
    }

    public function testNoRetryInsideFirstBackoffWindow(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2025-01-01T00:00:00Z'));
        $cache = new ArrayAdapter();
        $locks = new LockFactory(new InMemoryStore());

        $http = new MockHttpClient(new MockResponse('server error', ['http_code' => 500]));

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();

        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::SOFT_FAIL, 'tries' => 1], $state);

        $clock->sleep(60 - 1);
        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();
        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::SOFT_FAIL, 'tries' => 1], $state);
    }

    public function testRetryIsPerformedExactlyAtFirstBoundary(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2025-01-01T00:00:00Z'));
        $cache = new ArrayAdapter();
        $locks = new LockFactory(new InMemoryStore());

        $http = new MockHttpClient([
            new MockResponse('server error', ['http_code' => 500]),
            new MockResponse('server error', ['http_code' => 500]),
        ]);

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $verifier->verify($shopId);
        static::assertSame(1, $http->getRequestsCount());

        $clock->sleep(60);
        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();

        static::assertTrue($result);
        static::assertSame(2, $http->getRequestsCount());
        self::assertState(['status' => VerificationStatus::SOFT_FAIL, 'tries' => 2], $state);
    }

    public function testPassOnRetryAfterSoftFail(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2025-01-01T00:00:00Z'));
        $cache = new ArrayAdapter();
        $locks = new LockFactory(new InMemoryStore());

        $http = new MockHttpClient([
            new MockResponse('server error', ['http_code' => 500]),
            new MockResponse('', ['http_code' => 204]),
        ]);

        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();
        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::SOFT_FAIL], $state);

        $clock->sleep(60);
        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();

        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::PASS, 'tries' => 2], $state);
    }

    public function testSoftFailBackoffIsCappedAtOneHour(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2025-01-01T00:00:00Z'));
        $cache = new ArrayAdapter();
        $locks = new LockFactory(new InMemoryStore());

        $existingState = new VerificationState(
            VerificationStatus::SOFT_FAIL,
            10,
            $clock->now(),
            'server error'
        );

        $item = $cache->getItem(AppUrlVerifier::VERIFICATION_RESULT_CACHE_KEY);
        $item->set($existingState);
        $item->expiresAfter(60 * 60 * 24);
        $cache->save($item);

        $http = new MockHttpClient(new MockResponse('server error', ['http_code' => 500]));
        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();
        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::SOFT_FAIL, 'tries' => 10], $state);
        static::assertSame(0, $http->getRequestsCount());

        $clock->sleep(60 * 60 - 1);
        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();
        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::SOFT_FAIL, 'tries' => 10], $state);
        static::assertSame(0, $http->getRequestsCount());

        $clock->sleep(1);
        $result = $verifier->verify($shopId);
        $state = $verifier->getCurrentState();
        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::SOFT_FAIL, 'tries' => 11], $state);
        static::assertSame(1, $http->getRequestsCount());
    }

    public function testVerifyNowClearsOldStateAndRecomputes(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $locks = new LockFactory(new InMemoryStore());

        $state = new VerificationState(
            VerificationStatus::SOFT_FAIL,
            2,
            $clock->now(),
            'some error'
        );

        $item = $cache->getItem(AppUrlVerifier::VERIFICATION_RESULT_CACHE_KEY);
        $item->set($state);
        $item->expiresAfter(60 * 60 * 24);
        $cache->save($item);

        $http = new MockHttpClient(new MockResponse('', ['http_code' => 204]));
        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shop = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $state = $verifier->getCurrentState();
        self::assertState(['status' => VerificationStatus::SOFT_FAIL, 'tries' => 2], $state);

        $clock->sleep(2);

        $result = $verifier->forceVerify($shop);
        $state = $verifier->getCurrentState();
        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::PASS, 'tries' => 1, 'at' => $clock->now()], $state);
    }

    public function testVerifyNowDoesNothingAndReturnsTrueForNonProdEnvironments(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $locks = new LockFactory(new InMemoryStore());

        $http = new MockHttpClient();
        $verifier = new AppUrlVerifier('dev', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shop = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        static::assertTrue($verifier->forceVerify($shop));
        static::assertSame(0, $http->getRequestsCount());
    }

    public function testVerifyNowWithSkipEnvCheck(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $locks = new LockFactory(new InMemoryStore());

        $http = new MockHttpClient(new MockResponse('', ['http_code' => 204]));
        $verifier = new AppUrlVerifier('dev', '6.7.1.0', $cache, $http, $locks, $this->createMock(LoggerInterface::class), $clock);
        $shop = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $result = $verifier->forceVerify($shop, true);
        $state = $verifier->getCurrentState();
        static::assertTrue($result);
        self::assertState(['status' => VerificationStatus::PASS, 'tries' => 1, 'at' => $clock->now()], $state);
        static::assertSame(1, $http->getRequestsCount());
    }

    public function testGetCurrentStateReturnsNullWhenCacheEmpty(): void
    {
        $cache = new ArrayAdapter();
        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, new MockHttpClient(), new LockFactory(new InMemoryStore()), $this->createMock(LoggerInterface::class), new MockClock());

        static::assertNull($verifier->getCurrentState());
    }

    public function testGetCurrentState(): void
    {
        $cache = new ArrayAdapter();
        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, new MockHttpClient(), new LockFactory(new InMemoryStore()), $this->createMock(LoggerInterface::class), new MockClock());

        $state = new VerificationState(
            VerificationStatus::SOFT_FAIL,
            2,
            new \DateTimeImmutable('@1234567890'),
            'server busy'
        );

        $item = $cache->getItem(AppUrlVerifier::VERIFICATION_RESULT_CACHE_KEY);
        $item->set($state);
        $cache->save($item);

        static::assertEquals($state, $verifier->getCurrentState());
    }

    #[DataProvider('completeVerificationProvider')]
    public function testCompleteVerification(ArrayAdapter $cache, string $runId, string $token, bool $expectedResult): void
    {
        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, new MockHttpClient(), new LockFactory(new InMemoryStore()), $this->createMock(LoggerInterface::class), new MockClock());

        $result = $verifier->completeVerification($runId, $token);

        static::assertSame($expectedResult, $result);
    }

    public static function completeVerificationProvider(): \Generator
    {
        yield 'no-cache' => [
            new ArrayAdapter(),
            'randomid',
            bin2hex(random_bytes(16)),
            false,
        ];

        $cache = new ArrayAdapter();
        $cache->get('app_url_verify-randomid', fn () => bin2hex(random_bytes(14)));

        yield 'invalid-stored-token' => [
            $cache,
            'randomid',
            bin2hex(random_bytes(16)),
            false,
        ];

        $cache = new ArrayAdapter();
        $cache->get('app_url_verify-randomid', fn () => bin2hex(random_bytes(16)));

        yield 'invalid-user-token' => [
            $cache,
            'randomid',
            bin2hex(random_bytes(14)),
            false,
        ];

        $cache = new ArrayAdapter();
        $cache->get('app_url_verify-randomid', fn () => bin2hex(random_bytes(14)));

        yield 'both-tokens-invalid' => [
            $cache,
            'randomid',
            bin2hex(random_bytes(14)),
            false,
        ];

        $token = bin2hex(random_bytes(16));
        $cache = new ArrayAdapter();
        $cache->get('app_url_verify-randomid', fn () => $token);

        yield 'success-tokens-match' => [
            $cache,
            'randomid',
            $token,
            true,
        ];
    }

    public function testLogsOnHardFail(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $locks = new LockFactory(new InMemoryStore());
        $http = new MockHttpClient(new MockResponse('not found', ['http_code' => 404]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'App URL verification failed with status: "HARD_FAIL"',
                ['info' => 'Unexpected response from APP_URL verification endpoint: HTTP code: "404" body: "not found"', 'numTries' => 1]
            );

        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);
        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $logger, $clock);

        $result = $verifier->verify($shopId);
        static::assertFalse($result);
    }

    public function testLogsOnSoftFail(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock();
        $locks = new LockFactory(new InMemoryStore());
        $http = new MockHttpClient(new MockResponse('server error', ['http_code' => 500]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'App URL verification failed with status: "SOFT_FAIL"',
                ['info' => 'Unexpected response from APP_URL verification endpoint: HTTP code: "500" body: "server error"', 'numTries' => 1]
            );

        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);
        $verifier = new AppUrlVerifier('prod', '6.7.1.0', $cache, $http, $locks, $logger, $clock);

        $result = $verifier->verify($shopId);
        static::assertTrue($result);
    }

    /**
     * @return array{sleep: int, status: VerificationStatus, tries: int, httpCalls: int, return: bool}
     */
    private static function step(
        int $sleep,
        VerificationStatus $expectedStatus,
        int $expectedNumTries,
        int $expectedNumHttpCalls,
        bool $expectedReturn
    ): array {
        return [
            'sleep' => $sleep,
            'status' => $expectedStatus,
            'tries' => $expectedNumTries,
            'httpCalls' => $expectedNumHttpCalls,
            'return' => $expectedReturn,
        ];
    }

    /**
     * @param array{status: VerificationStatus, tries: int, return: bool, httpCalls: int} $step
     */
    private function assertStep(?VerificationState $state, array $step, bool $return, int $numHttpCalls): void
    {
        self::assertState(
            [
                'status' => $step['status'],
                'tries' => $step['tries'],
            ],
            $state,
        );

        static::assertSame($step['return'], $return);
        static::assertSame($step['httpCalls'], $numHttpCalls);
    }

    /**
     * @param array{status: VerificationStatus, tries?: int, at?: \DateTimeImmutable, info?: string|null} $expectedState
     */
    private static function assertState(array $expectedState, ?VerificationState $state): void
    {
        static::assertNotNull($state);
        static::assertSame($state->status, $expectedState['status']);

        static::assertSame($expectedState['tries'] ?? 1, $state->numTries);

        if (isset($expectedState['at'])) {
            static::assertSame($expectedState['at']->getTimestamp(), $state->at->getTimestamp());
        }

        if (isset($expectedState['info'])) {
            static::assertSame($expectedState['info'], $state->info);
        }
    }
}
