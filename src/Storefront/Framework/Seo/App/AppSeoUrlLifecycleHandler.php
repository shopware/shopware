<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function activate(AppActivationContext $context): void
    {
        $this->messageBus->dispatch(new AppSeoUrlSyncMessage($context->app->getId(), true));
    }

    public function update(AppPersistContext $context): void
    {
        if (!$context->app->isActive()) {
            return;
        }

        $this->messageBus->dispatch(new AppSeoUrlSyncMessage($context->app->getId(), true));
    }
}
