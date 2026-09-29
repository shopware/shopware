<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookHealthService::class)]
class WebhookHealthServiceTest extends TestCase
{
    public function testRecordTerminalFailureIsNoOpWhenWebhookNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $connection->expects($this->never())
            ->method('update');

        $service = new WebhookHealthService($connection);
        $service->recordFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureIsNoOpWhenWebhookInactive(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 0, 'error_count' => 3]);

        $connection->expects($this->never())
            ->method('update');

        $service = new WebhookHealthService($connection);
        $service->recordFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureIncrementsBelowThreshold(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 1, 'error_count' => 2]);

        $connection->expects($this->once())
            ->method('update')
            ->with('webhook', ['error_count' => 3], ['id' => Uuid::fromHexToBytes($webhookId)]);

        $service = new WebhookHealthService($connection);
        $service->recordFailure($webhookId, WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureDeactivatesAtThresholdWithDisableStrategy(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 1, 'error_count' => WebhookFailureStrategy::MAX_ERROR_COUNT - 1]);

        $connection->expects($this->once())
            ->method('update')
            ->with('webhook', ['error_count' => 0, 'active' => 0], ['id' => Uuid::fromHexToBytes($webhookId)]);

        $service = new WebhookHealthService($connection);
        $service->recordFailure($webhookId, WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureKeepsActiveWithIgnoreStrategyAboveThreshold(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 1, 'error_count' => WebhookFailureStrategy::MAX_ERROR_COUNT + 5]);

        $connection->expects($this->once())
            ->method('update')
            ->with(
                'webhook',
                ['error_count' => WebhookFailureStrategy::MAX_ERROR_COUNT + 6],
                ['id' => Uuid::fromHexToBytes($webhookId)]
            );

        $service = new WebhookHealthService($connection);
        $service->recordFailure($webhookId, WebhookFailureStrategy::Ignore);
    }

    public function testResetErrorCount(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('update')
            ->with('webhook', ['error_count' => 0], ['id' => Uuid::fromHexToBytes($webhookId)]);

        $service = new WebhookHealthService($connection);
        $service->resetErrorCount($webhookId);
    }
}
