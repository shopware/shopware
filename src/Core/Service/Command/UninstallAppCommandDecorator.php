<?php declare(strict_types=1);

namespace Shopware\Core\Service\Command;

use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Command\UninstallAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceStorage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
class UninstallAppCommandDecorator extends UninstallAppCommand
{
    public function __construct(
        AbstractAppLifecycle $appLifecycle,
        AppStorage $appStorage,
        private readonly ServiceStorage $serviceStorage,
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly LifecycleManager $lifecycleManager
    ) {
        parent::__construct($appLifecycle, $appStorage);
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The name of the app')]
        string $name,
        #[Option(description: 'Keep user data of the app')]
        bool $keepUserData = false,
        #[Option(description: 'Use this option to skip recompiling of all themes')]
        bool $skipThemeCompile = false,
    ): int {
        if ($this->lifecycleManager->enabled()) {
            return parent::__invoke($io, $name, $keepUserData, $skipThemeCompile);
        }

        $context = Context::createCLIContext();

        $service = $this->serviceStorage->findByName($name, $context);

        if ($service === null) {
            return parent::__invoke($io, $name, $keepUserData, $skipThemeCompile);
        }

        $this->serviceLifecycle->uninstall($service->name, $context);

        $io->success('App uninstalled successfully.');

        return self::SUCCESS;
    }
}
