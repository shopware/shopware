<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * @internal
 */
#[Package('framework')]
class SystemHeartbeatEvent implements Hookable
{
    final public const NAME = 'app.system_heartbeat';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{}
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
