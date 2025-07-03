<?php declare(strict_types=1);

namespace Shopware\Core\Service\MessageHandler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\Notification;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class InstallServicesHandler
{
    public function __construct(
        private LifecycleManager $manager,
        private Notification $notification,
    ) {
    }

    public function __invoke(InstallServicesMessage $installServicesMessage): void
    {
        $installed = $this->manager->install(Context::createDefaultContext());

        if (!empty($installed)) {
            $this->notification->newServicesInstalled();
        }
    }
}
