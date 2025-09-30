<?php declare(strict_types=1);

namespace Shopware\Core\Service\MessageHandler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class InstallServicesHandler
{
    public function __construct(private LifecycleManager $manager)
    {
    }

    public function __invoke(InstallServicesMessage $installServicesMessage): void
    {
        $this->manager->install(Context::createDefaultContext());
    }
}
