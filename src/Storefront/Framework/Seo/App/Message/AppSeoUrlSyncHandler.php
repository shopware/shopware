<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: AppSeoUrlSyncMessage::class)]
final class AppSeoUrlSyncHandler
{
    public function __construct(private readonly AppSeoUrlSynchronizer $synchronizer)
    {
    }

    public function __invoke(AppSeoUrlSyncMessage $message): void
    {
        $this->synchronizer->syncStaticRoutes($message->getAppId());

        if ($message->shouldRegenerateEntityRoutes()) {
            $this->synchronizer->regenerateEntityRoutes($message->getAppId());
        }
    }
}
