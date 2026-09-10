<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Shopware\Core\Framework\Webhook\WebhookEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookCollection::class)]
class WebhookCollectionTest extends TestCase
{
    public function testFilterForEvent(): void
    {
        $match = self::webhook('order', 'checkout.order.placed');

        $collection = new WebhookCollection([$match, self::webhook('customer', 'customer.registered')]);
        $filtered = $collection->filterForEvent('checkout.order.placed');

        static::assertSame([$match], array_values($filtered->getElements()));
    }

    public function testGetAclRoleIdsAsBinaryCollectsOnlyAppWebhooks(): void
    {
        $aclRoleId = Uuid::randomHex();

        $appWebhook = self::webhook('app-hook', 'checkout.order.placed');
        $app = new AppEntity();
        $app->setAclRoleId($aclRoleId);
        $appWebhook->setApp($app);

        $collection = new WebhookCollection([$appWebhook, self::webhook('plain', 'customer.registered')]);

        static::assertSame([Uuid::fromHexToBytes($aclRoleId)], $collection->getAclRoleIdsAsBinary());
    }

    public function testAllowedForDispatching(): void
    {
        $plain = self::webhook('plain', 'customer.registered');

        $activeApp = new AppEntity();
        $activeApp->setActive(true);
        $activeAppWebhook = self::webhook('active-app', 'checkout.order.placed');
        $activeAppWebhook->setApp($activeApp);

        $inactiveApp = new AppEntity();
        $inactiveApp->setActive(false);
        $inactiveAppWebhook = self::webhook('inactive-app', 'checkout.order.placed');
        $inactiveAppWebhook->setApp($inactiveApp);

        $lifecycleWebhook = self::webhook('lifecycle', AppUpdatedEvent::NAME);
        $inactiveAppForLifecycle = new AppEntity();
        $inactiveAppForLifecycle->setActive(false);
        $lifecycleWebhook->setApp($inactiveAppForLifecycle);

        $collection = new WebhookCollection([$plain, $activeAppWebhook, $inactiveAppWebhook, $lifecycleWebhook]);

        static::assertSame(
            ['plain', 'active-app', 'lifecycle'],
            array_keys($collection->allowedForDispatching()->getElements())
        );
    }

    private static function webhook(string $id, string $eventName): WebhookEntity
    {
        $webhook = new WebhookEntity();
        $webhook->setUniqueIdentifier($id);
        $webhook->setEventName($eventName);

        return $webhook;
    }
}
