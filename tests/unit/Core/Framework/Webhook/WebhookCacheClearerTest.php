<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\WebhookCacheClearer;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;

/**
 * @internal
 */
#[CoversClass(WebhookCacheClearer::class)]
class WebhookCacheClearerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            'webhook.written' => 'clearWebhookCache',
            'acl_role.written' => 'clearPrivilegesCache',
        ], WebhookCacheClearer::getSubscribedEvents());
    }

    public function testReset(): void
    {
        $dispatcherMock = $this->createMock(WebhookDispatcher::class);
        $dispatcherMock->expects(static::once())
            ->method('clearInternalWebhookCache');

        $dispatcherMock->expects(static::once())
            ->method('clearInternalPrivilegesCache');

        $cacheClearer = new WebhookCacheClearer($dispatcherMock);
        $cacheClearer->reset();
    }

    public function testClearWebhookCache(): void
    {
        $dispatcherMock = $this->createMock(WebhookDispatcher::class);
        $dispatcherMock->expects(static::once())
            ->method('clearInternalWebhookCache');

        $cacheClearer = new WebhookCacheClearer($dispatcherMock);
        $cacheClearer->clearWebhookCache();
    }

    public function testClearPrivilegesCache(): void
    {
        $dispatcherMock = $this->createMock(WebhookDispatcher::class);
        $dispatcherMock->expects(static::once())
            ->method('clearInternalPrivilegesCache');

        $cacheClearer = new WebhookCacheClearer($dispatcherMock);
        $cacheClearer->clearPrivilegesCache();
    }
}
