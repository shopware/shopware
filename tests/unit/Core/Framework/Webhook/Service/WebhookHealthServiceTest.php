<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;

/**
 * Covers the legacy (WEBHOOKS_REWORK-off) shared-counter path on {@see WebhookHealthService}. The
 * flag-on circuit-breaker state machine is covered by the integration matrix test.
 *
 * @internal
 */
#[CoversClass(WebhookHealthService::class)]
class WebhookHealthServiceTest extends TestCase
{
    public function testRecordLegacyFailureIsNoOpWhenWebhookNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())
            ->method('updateRelated');

        $this->makeService($connection, $relatedWebhooks)
            ->recordLegacyFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordLegacyFailureIsNoOpWhenWebhookInactive(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 0, 'error_count' => 3]);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())
            ->method('updateRelated');

        $this->makeService($connection, $relatedWebhooks)
            ->recordLegacyFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordLegacyFailureIncrementsBelowThreshold(): void
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

        $this->makeService($connection, $relatedWebhooks)
            ->recordLegacyFailure($webhookId, WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordLegacyFailureDeactivatesAtThresholdWithDisableStrategy(): void
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

        $this->makeService($connection, $relatedWebhooks)
            ->recordLegacyFailure($webhookId, WebhookFailureStrategy::DisableOnThreshold);
    }

    public function testRecordLegacyFailureKeepsActiveWithIgnoreStrategyAboveThreshold(): void
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

        $this->makeService($connection, $relatedWebhooks)
            ->recordLegacyFailure($webhookId, WebhookFailureStrategy::Ignore);
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

        $this->makeService($connection, $relatedWebhooks)->resetErrorCount($webhookId);
    }

    private function makeService(Connection $connection, RelatedWebhooks $relatedWebhooks): WebhookHealthService
    {
        // The legacy path uses only the connection + RelatedWebhooks; the flag-on dependencies are
        // stubbed (a real HealthConfig — production defaults — because it is final and self-validating).
        return new WebhookHealthService(
            $connection,
            $relatedWebhooks,
            $this->createMock(WebhookOutboxStore::class),
            new HealthConfig([300, 600, 1200, 2400, 3600, 14400], 5, 3, 7),
            $this->createMock(ClockInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}
