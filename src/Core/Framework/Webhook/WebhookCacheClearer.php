<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WebhookCacheClearer implements EventSubscriberInterface
{
    /**
     * @var WebhookDispatcher
     */
    private $dispatcher;

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

    public function clearWebhookCache(): void
    {
        $this->dispatcher->clearInternalWebhookCache();
    }

    public function clearPrivilegesCache(): void
    {
        $this->dispatcher->clearInternalPrivilegesCache();
    }
}
