<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\WebhookSubscription;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookSubscription::class)]
class WebhookSubscriptionTest extends TestCase
{
    public function testOnlyAnAppsWebhookBelongsToAnApp(): void
    {
        static::assertTrue((new WebhookSubscription(Uuid::randomHex(), SubscriberType::App, Uuid::randomHex(), 'product.written', null))->belongsToApp());
        static::assertFalse((new WebhookSubscription(Uuid::randomHex(), SubscriberType::User, Uuid::randomHex(), 'product.written', null))->belongsToApp());
    }

    public function testAUsersWebhookIsOwnedOnlyByThatUser(): void
    {
        $userId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), SubscriberType::User, $userId, 'product.written', null);

        static::assertTrue($subscription->isOwnedBy(new AdminApiSource($userId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(Uuid::randomHex())));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, $userId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($userId, Uuid::randomHex())));
    }

    public function testAnIntegrationsWebhookIsOwnedOnlyByThatIntegration(): void
    {
        $integrationId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), SubscriberType::Integration, $integrationId, 'product.written', null);

        static::assertTrue($subscription->isOwnedBy(new AdminApiSource(null, $integrationId)));
        static::assertTrue($subscription->isOwnedBy(new AdminApiSource(Uuid::randomHex(), $integrationId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, Uuid::randomHex())));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($integrationId)));
    }

    public function testAnAppsWebhookIsOwnedByNoApiCaller(): void
    {
        $appId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), SubscriberType::App, $appId, 'product.written', null);

        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($appId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, $appId)));
    }
}
