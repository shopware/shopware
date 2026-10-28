<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\Api\Acl\ApiCaller;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
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
