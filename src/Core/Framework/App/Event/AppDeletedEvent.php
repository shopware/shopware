<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class AppDeletedEvent extends Event implements ShopwareEvent, Hookable
{
    final public const NAME = 'app.deleted';

    public function __construct(
        private readonly string $appId,
        private readonly Context $context,
        private readonly bool $keepUserData = false
    ) {
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getWebhookPayload(): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $appId === $this->getAppId();
    }
}
