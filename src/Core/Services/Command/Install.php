<?php declare(strict_types=1);

namespace Shopware\Core\Services\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Services\AllServiceInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('core')]
#[AsCommand(
    name: 'services:install',
    description: 'Install all services'
)]
class Install extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly AllServiceInstaller $serviceInstaller)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $io->title('Installing services...');

        $installed = $this->serviceInstaller->install(Context::createCLIContext());

        if (empty($installed)) {
            $io->info('No services were installed');
        } else {
            $io->success(sprintf('Done. Installed %s', implode(', ', $installed)));
        }

        return Command::SUCCESS;
    }
}
