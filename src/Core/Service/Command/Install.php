<?php declare(strict_types=1);

namespace Shopware\Core\Service\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('framework')]
#[AsCommand(
    name: 'services:install',
    description: 'Install all services'
)]
class Install extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly LifecycleManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reinstall', null, InputOption::VALUE_NONE, 'Uninstall all services before installing them again');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $reinstall = (bool) $input->getOption('reinstall');

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
