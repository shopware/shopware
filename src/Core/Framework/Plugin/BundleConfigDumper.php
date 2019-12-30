<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Kernel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

class BundleConfigDumper implements EventSubscriberInterface
{
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    public function __construct(
        Kernel $kernel,
        EntityRepositoryInterface $pluginRepository
    ) {
        $this->kernel = $kernel;
        $this->pluginRepository = $pluginRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'dump',
            PluginPostDeactivateEvent::class => 'dump',
        ];
    }

    public function dump(): void
    {
        $config = $this->getConfig();

        file_put_contents(
            $this->kernel->getCacheDir() . '/../../plugins.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }

    private function getConfig(Bundle ...$additionalBundles): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepository->search($criteria, Context::createDefaultContext());
        $activePlugins = $plugins->map(function (PluginEntity $plugin) {
            return $plugin->getName();
        });

        $kernelBundles = array_merge($this->kernel->getBundles(), $additionalBundles);

        $projectDir = $this->kernel->getContainer()->getParameter('kernel.project_dir');

        $bundles = [];
        foreach ($kernelBundles as $bundle) {
            // only include shopware bundles
            if (!$bundle instanceof Bundle) {
                continue;
            }

            // dont include deactivated plugins
            if ($bundle instanceof Plugin && !in_array($bundle->getName(), $activePlugins, true)) {
                continue;
            }

            $path = $bundle->getPath();
            if (mb_strpos($bundle->getPath(), $projectDir) === 0) {
                // make relative
                $path = ltrim(mb_substr($path, mb_strlen($projectDir)), '/');
            }

            $bundles[$bundle->getName()] = [
                'basePath' => $path . '/',
                'views' => ['Resources/views'],
                'technicalName' => str_replace('_', '-', $bundle->getContainerPrefix()),
                'administration' => [
                    'path' => 'Resources/app/administration/src',
                    'entryFilePath' => $this->getEntryFile($bundle, 'Resources/app/administration/src'),
                    'webpack' => $this->getWebpackConfig($bundle, 'Resources/app/administration'),
                ],
                'storefront' => [
                    'path' => 'Resources/app/storefront/src',
                    'entryFilePath' => $this->getEntryFile($bundle, 'Resources/app/storefront/src'),
                    'webpack' => $this->getWebpackConfig($bundle, 'Resources/app/storefront'),
                    'styleFiles' => $this->getStyleFiles($bundle),
                ],
            ];
        }

        return $bundles;
    }

    private function getEntryFile(Bundle $bundle, string $componentPath): ?string
    {
        $path = trim($componentPath, '/');
        $absolutePath = $bundle->getPath() . '/' . $path;

        return file_exists($absolutePath . '/main.ts') ? $path . '/main.ts'
            : (file_exists($absolutePath . '/main.js') ? $path . '/main.js'
            : null);
    }

    private function getWebpackConfig(Bundle $bundle, string $componentPath): ?string
    {
        $path = trim($componentPath, '/');
        $absolutePath = $bundle->getPath() . '/' . $path;

        if (!file_exists($absolutePath . '/build/webpack.config.js')) {
            return null;
        }

        return $path . '/build/webpack.config.js';
    }

    private function getStyleFiles(Bundle $bundle): array
    {
        $files = [];
        if ($this->kernel->getContainer()->has('Shopware\Storefront\Theme\StorefrontPluginRegistry')) {
            $registry = $this->kernel->getContainer()->get('Shopware\Storefront\Theme\StorefrontPluginRegistry');

            $config = $registry->getConfigurations()->getByTechnicalName($bundle->getName());

            if ($config) {
                return $config->getStyleFiles()->getFilepaths();
            }
        }

        $path = $bundle->getPath() . DIRECTORY_SEPARATOR . 'Resources/app/storefront/src/scss';
        if (is_dir($path)) {
            $finder = new Finder();
            $finder->in($path)->files()->depth(0);

            foreach ($finder->getIterator() as $file) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
