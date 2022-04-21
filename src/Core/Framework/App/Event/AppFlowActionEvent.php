<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

class AppFlowActionEvent extends Event implements Hookable
{
    private string $name;

    private array $headers;

    private array $payload;

    public function __construct(string $name, array $headers, array $payload)
    {
        $this->name = $name;
        $this->headers = $headers;
        $this->payload = $payload;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWebhookHeaders(): array
    {
        return $this->headers;
    }

    public function getWebhookPayload(): array
    {
        return $this->payload;
    }

    /**
     * Apps don't need special ACL permissions for action, so this function always return true
     */
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
