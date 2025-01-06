<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\WebhookCacheClearer;

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
        $manager = $this->createMock(WebhookManager::class);
        $manager->expects(static::once())
            ->method('clearInternalWebhookCache');

        $manager->expects(static::once())
            ->method('clearInternalPrivilegesCache');

        $cacheClearer = new WebhookCacheClearer($manager);
        $cacheClearer->reset();
    }

    public function testClearWebhookCache(): void
    {
        $manager = $this->createMock(WebhookManager::class);
        $manager->expects(static::once())
            ->method('clearInternalWebhookCache');

        $cacheClearer = new WebhookCacheClearer($manager);
        $cacheClearer->clearWebhookCache();
    }

    public function testClearPrivilegesCache(): void
    {
        $manager = $this->createMock(WebhookManager::class);
        $manager->expects(static::once())
            ->method('clearInternalPrivilegesCache');

        $cacheClearer = new WebhookCacheClearer($manager);
        $cacheClearer->clearPrivilegesCache();
    }
}
