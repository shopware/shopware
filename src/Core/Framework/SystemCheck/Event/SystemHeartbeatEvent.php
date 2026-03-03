<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
class SystemHeartbeatEvent extends Event implements ShopwareEvent, Hookable
{
    final public const NAME = 'system.health.heartbeat';

    public function getContext(): Context
    {
        return $this->getContext();
    }

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
