<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\WebhookHealthTick;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookHealthTick::class)]
class WebhookHealthTickTest extends TestCase
{
    public function testRunsTheDutiesAndWritesTheHeartbeat(): void
    {
        $clock = new MockClock('2026-07-02 12:00:00');

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->once())
            ->method('set')
            ->with(WebhookHealthTick::HEARTBEAT_STORAGE_KEY, '2026-07-02 12:00:00.000');

        $healthService = $this->createMock(WebhookHealthService::class);
        $healthService->expects($this->once())->method('tick');

        $tick = new WebhookHealthTick($storage, $clock, new NullLogger(), $healthService);

        $tick->run();
    }

    public function testRunsAgainAtTheExactDebounceBoundary(): void
    {
        $clock = new MockClock('2026-07-02 12:00:00');

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->exactly(2))->method('set');

        $healthService = $this->createMock(WebhookHealthService::class);
        $healthService->expects($this->exactly(2))->method('tick');

        $tick = new WebhookHealthTick($storage, $clock, new NullLogger(), $healthService);

        $tick->run();
        $clock->modify('+59 seconds');
        $tick->run();
        $clock->modify('+1 second');
        $tick->run();
    }

    public function testLogsFailureAndDebouncesTheNextAttempt(): void
    {
        $exception = new \RuntimeException('duty blew up');
        $clock = new MockClock('2026-07-02 12:00:00');

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->never())->method('set');

        $healthService = $this->createMock(WebhookHealthService::class);
        $healthService->expects($this->once())->method('tick')->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Webhook health tick failed', ['exception' => $exception]);

        $tick = new WebhookHealthTick($storage, $clock, $logger, $healthService);

        $tick->run();
        $clock->modify('+59 seconds');
        $tick->run();
    }
}
