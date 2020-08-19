<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\WebhookCacheClearer;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;

class WebhookCacheClearerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            'webhook.written' => 'clearWebhookCache',
        ], WebhookCacheClearer::getSubscribedEvents());
    }

    public function testClearWebhookCache(): void
    {
        /** @var MockObject $dispatcherMock */
        $dispatcherMock = $this->createMock(WebhookDispatcher::class);
        $dispatcherMock->expects(static::once())
            ->method('clearInternalCache');

        $cacheClearer = new WebhookCacheClearer($dispatcherMock);
        $cacheClearer->clearWebhookCache();
    }
}
