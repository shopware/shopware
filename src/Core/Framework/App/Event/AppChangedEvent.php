<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AppChangedEvent extends Event implements ShopwareEvent, Hookable
{
    /**
     * @var AppEntity
     */
    private $app;

    /**
     * @var Context
     */
    private $context;

    public function __construct(AppEntity $app, Context $context)
    {
        $this->app = $app;
        $this->context = $context;
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
