<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\AssetValidation\AdministrationExtensionAssetValidator;
use Shopware\Core\Framework\Plugin\Util\AssetValidation\AdministrationExtensionAssetViolation;
use Shopware\Core\Installer\Installer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'assets:install',
    description: 'Installs bundles web assets under a public web directory',
)]
#[Package('framework')]
class AssetInstallCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly AssetService $assetService,
        private readonly ActiveAppsLoader $activeAppsLoader,
        private readonly AdministrationExtensionAssetValidator $administrationExtensionAssetValidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the install of assets regardless of the manifest state');
        $this->addOption('strict-extension-assets', null, InputOption::VALUE_NONE, 'Fail when Administration extension asset metadata references missing or invalid local files');
    }

    /**
     * @throws \JsonException
     * @throws UnableToDeleteDirectory
     * @throws UnableToCreateDirectory
     * @throws UnableToCheckExistence
     * @throws FilesystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('Copying assets');

        $assetViolations = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            $io->writeln(\sprintf('Copying files for bundle: %s', $bundle->getName()));
            $this->assetService->copyAssets($bundle, $input->getOption('force'));

            if ($bundle instanceof Bundle) {
                array_push($assetViolations, ...$this->validateAdministrationExtensionAssets($bundle, $io));
            }
        }

        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $io->writeln(\sprintf('Copying files for app: %s', $app['name']));
            $this->assetService->copyAssetsFromApp($app['name'], $app['path'], $input->getOption('force'));
        }

        $io->writeln('Copying files for bundle: Installer');
        $this->assetService->copyAssets(new Installer(), $input->getOption('force'));

        $publicDir = $this->kernel->getProjectDir() . '/public/';
        if (!\is_file($publicDir . '/.htaccess') && \is_file($publicDir . '/.htaccess.dist')) {
            $io->writeln('Copying .htaccess.dist to .htaccess');
            copy($publicDir . '/.htaccess.dist', $publicDir . '/.htaccess');
        }

        if ($assetViolations !== [] && $input->getOption('strict-extension-assets')) {
            $io->error('Administration extension asset validation failed.');

            return self::FAILURE;
        }

        $io->success('Successfully copied all bundle files');

        return self::SUCCESS;
    }

    /**
     * @return list<AdministrationExtensionAssetViolation>
     */
    private function validateAdministrationExtensionAssets(Bundle $bundle, ShopwareStyle $io): array
    {
        $violations = $this->administrationExtensionAssetValidator->validateEntrypointsFile($bundle);

        foreach ($violations as $violation) {
            $io->warning($violation->toConsoleMessage());
        }

        return $violations;
    }
}
