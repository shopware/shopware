<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('core')]
class WebhookCacheClearer implements EventSubscriberInterface, ResetInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly WebhookDispatcher $dispatcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'webhook.written' => 'clearWebhookCache',
            'acl_role.written' => 'clearPrivilegesCache',
        ];
    }

    /**
     * Reset can not be handled by the Dispatcher itself, as it may be in the middle of a decoration chain
     * Therefore tagging that service directly won't work
     */
    public function reset(): void
    {
        $this->clearWebhookCache();
        $this->clearPrivilegesCache();
    }

    public function clearWebhookCache(): void
    {
        $this->dispatcher->clearInternalWebhookCache();
    }

    public function clearPrivilegesCache(): void
    {
        $this->dispatcher->clearInternalPrivilegesCache();
    }
}
