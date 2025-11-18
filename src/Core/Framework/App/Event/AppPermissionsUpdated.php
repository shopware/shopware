<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class AppPermissionsUpdated extends Event implements ShopwareEvent, Hookable
{
    final public const NAME = 'app.permissions.updated';

    /**
     * @param array<string> $permissions
     */
    public function __construct(
        public readonly string $appId,
        public readonly array $permissions,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'permissions' => $this->permissions,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $appId === $this->appId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
