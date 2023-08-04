<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\ScheduledTask;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler
 */
class CleanupWebhookEventLogTaskHandlerTest extends TestCase
{
    public function testHandler(): void
    {
        $cleaner = $this->createMock(WebhookCleanup::class);

        $cleaner->expects(static::once())->method('removeOldLogs');

        $handler = new CleanupWebhookEventLogTaskHandler(
            $this->createMock(EntityRepository::class),
            $cleaner
        );

        $handler->run();
    }
}
