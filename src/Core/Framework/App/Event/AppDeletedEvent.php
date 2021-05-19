<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppDeletedEvent extends Event implements ShopwareEvent, Hookable
{
    public const NAME = 'app.deleted';

    private string $appId;

    private Context $context;

    private bool $keepUserData;

    public function __construct(string $appId, Context $context, bool $keepUserData = false)
    {
        $this->appId = $appId;
        $this->context = $context;
        $this->keepUserData = $keepUserData;
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
