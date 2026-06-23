<?php declare(strict_types=1);

namespace Shopware\Core\Service\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('framework')]
#[AsCommand(
    name: 'services:install',
    description: 'Install all services'
)]
class Install
{
    /**
     * @internal
     */
    public function __construct(private readonly LifecycleManager $manager)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Uninstall all services before installing them again')]
        bool $reinstall = false,
    ): int {
        $io->title($reinstall ? 'Reinstalling services...' : 'Installing services...');

        if (!$this->manager->enabled()) {
            $io->error('Services are disabled. Please enable them to install services.');

            return Command::FAILURE;
        }

        $installed = $reinstall
            ? $this->manager->reinstall(Context::createCLIContext())
            : $this->manager->install(Context::createCLIContext());

        if ($installed === []) {
            $io->info('No services were installed');
        } else {
            $io->success(\sprintf('Done. Installed %s', implode(', ', $installed)));
        }

        return Command::SUCCESS;
    }
}
