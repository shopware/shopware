<?php declare(strict_types=1);

namespace Shopware\Core\Service\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Manager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: InstallServicesTask::class)]
final class InstallServicesTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly AllServiceInstaller $serviceInstaller,
        private readonly Manager $manager,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        if ($this->manager->isDisabled()) {
            return;
        }

        $this->serviceInstaller->install(Context::createCLIContext());
    }
}
