<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookStateRepository;

/**
 * @internal
 */
#[CoversClass(WebhookStateRepository::class)]
class WebhookStateRepositoryTest extends TestCase
{
    public function testIncrementErrorCountReturnsZeroWhenWebhookNotFound(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())
            ->method('updateRelated');

        $repository = new WebhookStateRepository($connection, $relatedWebhooks);

        static::assertSame(0, $repository->incrementErrorCount($webhookId));
    }

    public function testIncrementErrorCountReturnsZeroWhenWebhookInactive(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active' => 0, 'error_count' => 3]);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())
            ->method('updateRelated');

        $repository = new WebhookStateRepository($connection, $relatedWebhooks);

        static::assertSame(0, $repository->incrementErrorCount($webhookId));
    }

    public function testIncrementErrorCountIncrementsAndReturnsNewCount(): void
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

        $repository = new WebhookStateRepository($connection, $relatedWebhooks);

        static::assertSame(3, $repository->incrementErrorCount($webhookId));
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

        $repository = new WebhookStateRepository($connection, $relatedWebhooks);
        $repository->resetErrorCount($webhookId);
    }

    public function testDeactivate(): void
    {
        $webhookId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->once())
            ->method('updateRelated')
            ->with(
                $webhookId,
                ['error_count' => 0, 'active' => 0],
                static::isInstanceOf(Context::class)
            );

        $repository = new WebhookStateRepository($connection, $relatedWebhooks);
        $repository->deactivate($webhookId);
    }
}
