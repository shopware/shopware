<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Installer\Installer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'assets:install',
    description: 'Installs bundles web assets under a public web directory',
)]
#[Package('core')]
class AssetInstallCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly AssetService $assetService,
        private readonly ActiveAppsLoader $activeAppsLoader
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        foreach ($this->kernel->getBundles() as $bundle) {
            $io->writeln(sprintf('Copying files for bundle: %s', $bundle->getName()));
            $this->assetService->copyAssetsFromBundle($bundle->getName());
        }

        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $io->writeln(sprintf('Copying files for app: %s', $app['name']));
            $this->assetService->copyAssetsFromApp($app['name'], $app['path']);
        }

        $io->writeln('Copying files for bundle: Installer');
        $this->assetService->copyAssets(new Installer());

        $publicDir = $this->kernel->getProjectDir() . '/public/';

        if (!file_exists($publicDir . '/.htaccess') && file_exists($publicDir . '/.htaccess.dist')) {
            $io->writeln('Copying .htaccess.dist to .htaccess');
            copy($publicDir . '/.htaccess.dist', $publicDir . '/.htaccess');
        }

        $io->success('Successfully copied all bundle files');

        return self::SUCCESS;
    }
}
