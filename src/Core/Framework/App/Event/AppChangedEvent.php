<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
abstract class AppChangedEvent extends Event implements ShopwareEvent, Hookable
{
    public function __construct(
        private readonly AppEntity $app,
        private readonly Context $context
    ) {
    }

    abstract public function getName(): string;

    public function getApp(): AppEntity
    {
        return $this->app;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getWebhookPayload(): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $appId === $this->app->getId();
    }
}
