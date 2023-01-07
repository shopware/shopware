<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @package core
 *
 * @internal
 */
class WebhookCacheClearer implements EventSubscriberInterface, ResetInterface
{
    private WebhookDispatcher $dispatcher;

    /**
     * @internal
     */
    public function __construct(WebhookDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
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
