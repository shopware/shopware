<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class AppFlowActionEvent extends Event implements Hookable
{
    /**
     * @param array<string, string> $headers
     * @param array<mixed> $payload
     */
    public function __construct(
        private readonly string $name,
        private readonly array $headers,
        private readonly array $payload
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getWebhookHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<mixed>
     */
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
