<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\Api\Acl\ApiCaller;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;

/**
 * Who a webhook belongs to, which event it subscribes to, and which roles it is limited to.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookSubscription
{
    /**
     * @param string $ownerId the id of the app, user or integration, depending on $ownerType
     * @param list<string>|null $aclRoleIds null for all of the owner's roles
     */
    public function __construct(
        public string $webhookId,
        public SubscriberType $ownerType,
        public string $ownerId,
        public string $eventName,
        public ?array $aclRoleIds,
    ) {
    }

    /**
     * The owner is the app, else the integration, else the user.
     *
     * @param array<string, mixed> $row the webhook's columns in storage format, keyed by column name
     *
     * @return self|null null for a webhook without an owner
     */
    public static function fromRow(array $row): ?self
    {
        $ownerId = $row['app_id'] ?? $row['owner_integration_id'] ?? $row['owner_user_id'];
        if ($ownerId === null) {
            return null;
        }

        $ownerType = match (true) {
            $row['app_id'] !== null => SubscriberType::App,
            $row['owner_integration_id'] !== null => SubscriberType::Integration,
            default => SubscriberType::User,
        };

        return new self(
            Uuid::fromBytesToHex($row['id']),
            $ownerType,
            Uuid::fromBytesToHex($ownerId),
            $row['event_name'],
            $row['acl_role_ids'] !== null ? Json::decodeToList($row['acl_role_ids']) : null,
        );
    }

    public function belongsToApp(): bool
    {
        return $this->ownerType === SubscriberType::App;
    }

    public function isOwnedBy(AdminApiSource $source): bool
    {
        $caller = ApiCaller::of($source);

        return $caller !== null && $this->ownerType === SubscriberType::of($caller) && $this->ownerId === $caller->id;
    }
}
