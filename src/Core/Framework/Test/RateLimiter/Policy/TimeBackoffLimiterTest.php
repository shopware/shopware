<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\RateLimiter\Policy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoff;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Util\TimeUtil;

class TimeBackoffLimiterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;
    use SalesChannelApiTestBehaviour;

    private array $config;

    private int $attempts;

    private LimiterInterface $limiter;

    private string $id;

    private TestDataCollection $ids;

    private KernelBrowser $browser;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_13795', $this);

        $this->config = [
            'id' => 'test_limit',
            'policy' => 'time_backoff',
            'reset' => '5 minutes',
            'limits' => [
                [
                    'limit' => 3,
                    'interval' => '10 seconds',
                ],
                [
                    'limit' => 5,
                    'interval' => '30 seconds',
                ],
                [
                    'limit' => 7,
                    'interval' => '60 seconds',
                ],
            ],
        ];

        $this->id = $this->config['id'] . '-test';

        $factory = new RateLimiterFactory(
            $this->config,
            new CacheStorage(new ArrayAdapter()),
            $this->createMock(LockFactory::class)
        );

        $this->limiter = $factory->create('example');
        $this->limiter->reset();

        $this->getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testConsume(): void
    {
        $limit = $this->limiter->consume();
        static::assertTrue($limit->isAccepted());

        $this->limiter->reset();

        $limit = $this->limiter->consume(3);
        static::assertTrue($limit->isAccepted());

        $limit = $this->limiter->consume();
        static::assertFalse($limit->isAccepted());
    }

    /**
     * @dataProvider exceptionData
     */
    public function testConsumeThrowsCorrectException(int $consume, int $maxLimit, ?int $consumeBefore = null): void
    {
        if ($consumeBefore !== null) {
            $limit = $this->limiter->consume($consumeBefore);
            static::assertTrue($limit->isAccepted());
        }

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage(sprintf('Cannot reserve more tokens (%d) than the size of the rate limiter (%d)', $consume, $maxLimit));
        $this->limiter->consume($consume);
    }

    public function testReserve(): void
    {
        static::expectException(ReserveNotSupportedException::class);
        $this->limiter->reserve();
    }

    public function testThrottle(): void
    {
        $backoff = new TimeBackoff($this->id, $this->config['limits']);

        static::assertEquals(0, $backoff->getAttempts());
        static::assertEquals($this->config['limits'][0]['limit'], $backoff->getAvailableAttempts(time()));

        $backoff->setTimer(time());
        $backoff->setAttempts(3);

        static::assertEquals(3, $backoff->getAttempts());

        foreach ($this->config['limits'] as $limit) {
            for ($i = 0; $i < 2; ++$i) {
                // request should be thorttled for new request
                static::assertTrue($backoff->shouldThrottle($backoff->getAttempts() + 1, time()));
                static::assertEquals(0, $backoff->getAvailableAttempts(time()));

                // after wait time, request could be send again
                static::assertFalse($backoff->shouldThrottle($backoff->getAttempts() + 1, time() + $this->intervalToSeconds($limit['interval'])));

                // new request sent
                $backoff->setTimer(time());
                $backoff->setAttempts($backoff->getAttempts() + 1);

                // request should be thorttled again
                static::assertTrue($backoff->shouldThrottle($backoff->getAttempts(), time()));
                static::assertEquals(0, $backoff->getAvailableAttempts(time()));

                // after wait time, request could be send again
                $time = time() + $this->intervalToSeconds($limit['interval']);
                static::assertFalse($backoff->shouldThrottle($backoff->getAttempts(), $time));
                static::assertEquals(1, $backoff->getAvailableAttempts($time));
            }
        }
    }

    public function exceptionData(): \Generator
    {
        yield 'consume 4 and expect 3' => [4, 3];
        yield 'consume 2, then consoume 2 and expect 1' => [2, 1, 2];
        yield 'consume 3, then consume 2 and expect 1' => [2, 1, 3];
    }

    private function intervalToSeconds(string $interval): int
    {
        return TimeUtil::dateIntervalToSeconds((new \DateTimeImmutable())->diff(new \DateTimeImmutable('+' . $interval)));
    }
}
