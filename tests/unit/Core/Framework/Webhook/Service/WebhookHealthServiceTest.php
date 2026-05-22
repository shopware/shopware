<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;

/**
 * @internal
 */
#[CoversClass(WebhookHealthService::class)]
class WebhookHealthServiceTest extends TestCase
{
    public function testRecordTerminalFailureIsNoOpWhenWebhookNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())
            ->method('updateRelated');

        $service = new WebhookHealthService($connection, $relatedWebhooks);
        $service->recordFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureIsNoOpWhenWebhookInactive(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 0, 'error_count' => 3]);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())
            ->method('updateRelated');

        $service = new WebhookHealthService($connection, $relatedWebhooks);
        $service->recordFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureIncrementsBelowThreshold(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 1, 'error_count' => 2]);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->once())
            ->method('updateRelated')
            ->with(
                $webhookId,
                ['error_count' => 3],
                static::isInstanceOf(Context::class)
            );

        $service = new WebhookHealthService($connection, $relatedWebhooks);
        $service->recordFailure($webhookId, WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureDeactivatesAtThresholdWithDisableStrategy(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 1, 'error_count' => WebhookFailureStrategy::MAX_ERROR_COUNT - 1]);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->once())
            ->method('updateRelated')
            ->with(
                $webhookId,
                ['error_count' => 0, 'active' => 0],
                static::isInstanceOf(Context::class)
            );

        $service = new WebhookHealthService($connection, $relatedWebhooks);
        $service->recordFailure($webhookId, WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordTerminalFailureKeepsActiveWithIgnoreStrategyAboveThreshold(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 1, 'error_count' => WebhookFailureStrategy::MAX_ERROR_COUNT + 5]);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->once())
            ->method('updateRelated')
            ->with(
                $webhookId,
                ['error_count' => WebhookFailureStrategy::MAX_ERROR_COUNT + 6],
                static::isInstanceOf(Context::class)
            );

        $service = new WebhookHealthService($connection, $relatedWebhooks);
        $service->recordFailure($webhookId, WebhookFailureStrategy::Ignore);
    }

    public function testResetErrorCount(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->once())
            ->method('updateRelated')
            ->with(
                $webhookId,
                ['error_count' => 0],
                static::isInstanceOf(Context::class)
            );

        $service = new WebhookHealthService($connection, $relatedWebhooks);
        $service->resetErrorCount($webhookId);
    }
}
