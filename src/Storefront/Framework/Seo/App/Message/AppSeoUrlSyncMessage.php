<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\DeduplicatableMessageInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlSyncMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    public function __construct(
        private readonly ?string $appId = null,
        private readonly bool $regenerateEntityRoutes = false,
    ) {
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function shouldRegenerateEntityRoutes(): bool
    {
        return $this->regenerateEntityRoutes;
    }

    public function deduplicationId(): ?string
    {
        return ($this->appId ?? 'all') . ($this->regenerateEntityRoutes ? ':full' : ':static');
    }
}
