<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AdministrationDumpBundlesCommand extends Command
{
    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        parent::__construct();
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('administration:dump:bundles')
            ->setAliases(['administration:dump:plugins'])
            ->setDescription('Creating json file with path config for administration modules from bundles.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->searchBundleDirectories();

        $style = new SymfonyStyle($input, $output);
        $style->success('Successfully dumped administration modules configuration');
    }

    protected function searchBundleDirectories(): void
    {
        $bundles = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            // only include shopware bundles
            if (!$bundle instanceof Bundle) {
                continue;
            }
            // dont include deactivated plugins
            if ($bundle instanceof Plugin && !$bundle->isActive()) {
                continue;
            }
            $bundleName = $bundle->getName();

            $adminEntryPath = trim($bundle->getAdministrationEntryPath(), '/');
            // First try to load the main.js, otherwise try the main.ts
            $indexFile = $this->locateResource($bundleName, '/' . $adminEntryPath . '/main.js')
                ?? $this->locateResource($bundleName, '/' . $adminEntryPath . '/main.ts');

            if (!$indexFile) {
                continue;
            }

            $baseDirectory = $this->locateResource($bundleName);
            $customWebPackConfig = $this->locateResource($bundleName, '/' . $adminEntryPath . '/build/webpack.config.js');

            // return the path relative to the project dir
            $bundles[$bundleName] = [
                'basePath' => rtrim($baseDirectory, '/') . '/',
                'viewPath' => $this->locateResource($bundleName, '/' . $adminEntryPath) . '/',
                'entry' => $this->getPathRelativeToProjectDir($indexFile),
                'webpackConfig' => $customWebPackConfig ? $this->getPathRelativeToProjectDir($customWebPackConfig) : false,
            ];
        }

        file_put_contents(
            $this->kernel->getCacheDir() . '/../../config_administration_plugins.json',
            json_encode($bundles)
        );
    }

    private function locateResource(string $bundleName, string $path = ''): ?string
    {
        try {
            return $this->kernel->locateResource('@' . $bundleName . $path);
        } catch (\Exception $e) {
        }

        return null;
    }

    /**
     * return a path relative to the project dir
     */
    private function getPathRelativeToProjectDir(string $absolute): string
    {
        $projectDir = $this->kernel->getProjectDir();
        $relative = str_replace($projectDir, '', $absolute);
        $relative = ltrim($relative, '/');

        return $relative;
    }
}
