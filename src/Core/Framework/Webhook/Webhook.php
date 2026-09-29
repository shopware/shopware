<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\OwnerType;

/**
 * Simple DTO for internal use
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class Webhook
{
    /**
     * @param list<string> $ownerRoleIds ACL roles the webhook is authorized with, resolved live at load: the owner's roles, limited to the webhook's aclRoleIds when set; for an app webhook the owner is the app's integration
     * @param list<string>|null $aclRoleIds the roles the webhook is limited to, null when it uses all of its owner's roles
     */
    public function __construct(
        public string $id,
        public string $webhookName,
        public string $eventName,
        public string $url,
        public bool $onlyLiveVersion,
        public ?string $appId,
        public ?string $appName,
        public ?string $appSourceType,
        public bool $appActive,
        public ?string $appVersion,
        public ?string $appSecret,
        public OwnerType $ownerType = OwnerType::Restricted,
        public array $ownerRoleIds = [],
        public ?array $aclRoleIds = null,
    ) {
    }
}
