<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupWebhookEventLogTaskHandler::class)]
class CleanupWebhookEventLogTaskHandlerTest extends TestCase
{
    public function testHandler(): void
    {
        $cleaner = $this->createMock(WebhookCleanup::class);

        $cleaner->expects($this->once())->method('removeOldLogs');

        $handler = new CleanupWebhookEventLogTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $cleaner
        );

        $handler->run();
    }
}
