<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\IdentityType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
        static::assertTrue((new WebhookSubscription(Uuid::randomHex(), IdentityType::App, Uuid::randomHex(), 'product.written', null))->belongsToApp());
        static::assertFalse((new WebhookSubscription(Uuid::randomHex(), IdentityType::User, Uuid::randomHex(), 'product.written', null))->belongsToApp());
    }

    public function testAUsersWebhookIsOwnedOnlyByThatUser(): void
    {
        $userId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), IdentityType::User, $userId, 'product.written', null);

        static::assertTrue($subscription->isOwnedBy(new AdminApiSource($userId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(Uuid::randomHex())));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, $userId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($userId, Uuid::randomHex())));
    }

    public function testAnIntegrationsWebhookIsOwnedOnlyByThatIntegration(): void
    {
        $integrationId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), IdentityType::Integration, $integrationId, 'product.written', null);

        static::assertTrue($subscription->isOwnedBy(new AdminApiSource(null, $integrationId)));
        static::assertTrue($subscription->isOwnedBy(new AdminApiSource(Uuid::randomHex(), $integrationId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, Uuid::randomHex())));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($integrationId)));
    }

    public function testAnAppsWebhookIsOwnedByNoApiCaller(): void
    {
        $appId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), IdentityType::App, $appId, 'product.written', null);

        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($appId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, $appId)));
    }

    public function testAUsersWebhookIsReadFromItsRow(): void
    {
        $webhookId = Uuid::randomHex();
        $userId = Uuid::randomHex();

        static::assertEquals(
            new WebhookSubscription($webhookId, IdentityType::User, $userId, 'product.written', ['role']),
            WebhookSubscription::fromRow($this->row($webhookId, ownerUserId: $userId, aclRoleIds: '["role"]'))
        );
    }

    public function testTheAppOwnsAWebhookBeforeAnIntegrationOrUser(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        static::assertEquals(
            new WebhookSubscription($webhookId, IdentityType::App, $appId, 'product.written', null),
            WebhookSubscription::fromRow($this->row($webhookId, appId: $appId, ownerIntegrationId: Uuid::randomHex(), ownerUserId: Uuid::randomHex()))
        );
    }

    public function testTheIntegrationOwnsAWebhookBeforeTheUser(): void
    {
        $webhookId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        static::assertEquals(
            new WebhookSubscription($webhookId, IdentityType::Integration, $integrationId, 'product.written', null),
            WebhookSubscription::fromRow($this->row($webhookId, ownerIntegrationId: $integrationId, ownerUserId: Uuid::randomHex()))
        );
    }

    public function testAWebhookWithoutAnAppOrOwnerHasNoSubscription(): void
    {
        static::assertNull(WebhookSubscription::fromRow($this->row(Uuid::randomHex())));
    }

    /**
     * @return array<string, string|null>
     */
    private function row(string $webhookId, ?string $appId = null, ?string $ownerIntegrationId = null, ?string $ownerUserId = null, ?string $aclRoleIds = null): array
    {
        return [
            'id' => Uuid::fromHexToBytes($webhookId),
            'app_id' => $appId === null ? null : Uuid::fromHexToBytes($appId),
            'owner_integration_id' => $ownerIntegrationId === null ? null : Uuid::fromHexToBytes($ownerIntegrationId),
            'owner_user_id' => $ownerUserId === null ? null : Uuid::fromHexToBytes($ownerUserId),
            'event_name' => 'product.written',
            'acl_role_ids' => $aclRoleIds,
        ];
    }
}
