<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @Decoratable
 */
class BundleConfigGenerator implements BundleConfigGeneratorInterface
{
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    /**
     * @var ActiveAppsLoader
     */
    private $activeAppsLoader;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(
        Kernel $kernel,
        EntityRepositoryInterface $pluginRepository,
        ActiveAppsLoader $activeAppsLoader
    ) {
        $this->kernel = $kernel;
        $this->pluginRepository = $pluginRepository;
        $this->activeAppsLoader = $activeAppsLoader;

        $projectDir = $this->kernel->getContainer()->getParameter('kernel.project_dir');
        if (!\is_string($projectDir)) {
            throw new \RuntimeException('Container parameter "kernel.project_dir" needs to be a string');
        }
        $this->projectDir = $projectDir;
    }

    public function getConfig(): array
    {
        return array_merge($this->generatePluginConfigs(), $this->generateAppConfigs());
    }

    private function generatePluginConfigs(): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepository->search($criteria, Context::createDefaultContext());
        $activePlugins = $plugins->map(function (PluginEntity $plugin) {
            return $plugin->getName();
        });

        $kernelBundles = $this->kernel->getBundles();

        $bundles = [];
        foreach ($kernelBundles as $bundle) {
            // only include shopware bundles
            if (!$bundle instanceof Bundle) {
                continue;
            }

            // dont include deactivated plugins
            if ($bundle instanceof Plugin && !\in_array($bundle->getName(), $activePlugins, true)) {
                continue;
            }

            $path = $bundle->getPath();
            if (mb_strpos($bundle->getPath(), $this->projectDir) === 0) {
                // make relative
                $path = ltrim(mb_substr($path, mb_strlen($this->projectDir)), '/');
            }

            $bundles[$bundle->getName()] = [
                'basePath' => $path . '/',
                'views' => ['Resources/views'],
                'technicalName' => str_replace('_', '-', $bundle->getContainerPrefix()),
                'administration' => [
                    'path' => 'Resources/app/administration/src',
                    'entryFilePath' => $this->getEntryFile($bundle->getPath(), 'Resources/app/administration/src'),
                    'webpack' => $this->getWebpackConfig($bundle->getPath(), 'Resources/app/administration'),
                ],
                'storefront' => [
                    'path' => 'Resources/app/storefront/src',
                    'entryFilePath' => $this->getEntryFile($bundle->getPath(), 'Resources/app/storefront/src'),
                    'webpack' => $this->getWebpackConfig($bundle->getPath(), 'Resources/app/storefront'),
                    'styleFiles' => $this->getStyleFiles($bundle->getName()),
                ],
            ];
        }

        return $bundles;
    }

    private function generateAppConfigs(): array
    {
        $configs = [];
        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $absolutePath = $this->projectDir . '/' . $app['path'];

            $configs[$app['name']] = [
                'basePath' => $app['path'] . '/',
                'views' => ['Resources/views'],
                'technicalName' => str_replace('_', '-', $this->asSnakeCase($app['name'])),
                'storefront' => [
                    'path' => 'Resources/app/storefront/src',
                    'entryFilePath' => $this->getEntryFile($absolutePath, 'Resources/app/storefront/src'),
                    'webpack' => $this->getWebpackConfig($absolutePath, 'Resources/app/storefront'),
                    'styleFiles' => $this->getStyleFiles($app['name']),
                ],
            ];
        }

        return $configs;
    }

    private function getEntryFile(string $rootPath, string $componentPath): ?string
    {
        $path = trim($componentPath, '/');
        $absolutePath = $rootPath . '/' . $path;

        return file_exists($absolutePath . '/main.ts') ? $path . '/main.ts'
            : (file_exists($absolutePath . '/main.js') ? $path . '/main.js'
            : null);
    }

    private function getWebpackConfig(string $rootPath, string $componentPath): ?string
    {
        $path = trim($componentPath, '/');
        $absolutePath = $rootPath . '/' . $path;

        if (!file_exists($absolutePath . '/build/webpack.config.js')) {
            return null;
        }

        if (mb_strpos($path, $this->projectDir) === 0) {
            // make relative
            $path = ltrim(mb_substr($path, mb_strlen($this->projectDir)), '/');
        }

        return $path . '/build/webpack.config.js';
    }

    private function getStyleFiles(string $technicalName): array
    {
        if (!$this->kernel->getContainer()->has('Shopware\Storefront\Theme\StorefrontPluginRegistry')) {
            return [];
        }

        $registry = $this->kernel->getContainer()->get('Shopware\Storefront\Theme\StorefrontPluginRegistry');
        $config = $registry->getConfigurations()->getByTechnicalName($technicalName);

        if (!$config) {
            return [];
        }

        return array_map(function (string $path) {
            if (mb_strpos($path, $this->projectDir) === 0) {
                // make relative
                $path = ltrim(mb_substr($path, mb_strlen($this->projectDir)), '/');
            }

            return $path;
        }, $config->getStyleFiles()->getFilepaths());
    }

    private function asSnakeCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($string);
    }
}
