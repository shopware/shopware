<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Kernel;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[Package('framework')]
class ThemeFilesystemResolver
{
    /**
     * @var list<string>
     */
    private readonly array $bundleNames;

    public function __construct(
        private readonly SourceResolver $sourceResolver,
        private readonly Kernel $kernel,
    ) {
        // get all bundles + plugin names
        // we need to do this because at boot a plugin might not be active (eg during plugin:activate command) and thus not in `getBundles()`
        // but getPluginInstances does not include local bundles (eg Storefront)
        $this->bundleNames = array_values(array_unique(array_merge(
            array_keys($this->kernel->getBundles()),
            array_map(fn (Plugin $plugin): string => $plugin->getName(), $this->kernel->getPluginLoader()->getPluginInstances()->all())
        )));
    }

    public function getFilesystemForStorefrontConfig(StorefrontPluginConfiguration $configuration): Filesystem
    {
        $technicalName = $configuration->getTechnicalName();

        // Check if it's a known bundle (exact match)
        if (\in_array($technicalName, $this->bundleNames, true)) {
            try {
                $bundle = $this->kernel->getBundle($technicalName);
            } catch (\InvalidArgumentException) {
                $bundles = $this->kernel->getPluginLoader()
                    ->getPluginInstances()
                    ->filter(fn (Plugin $plugin) => $plugin->getName() === $technicalName)
                    ->all();

                $bundle = array_values($bundles)[0];
            }

            \assert($bundle instanceof BundleInterface);

            return new Filesystem($bundle->getPath());
        }

        // Check if it's a multi-theme variant (e.g., "StorefrontExperience" from "Storefront" bundle)
        foreach ($this->bundleNames as $bundleName) {
            if (str_starts_with($technicalName, $bundleName)) {
                try {
                    $bundle = $this->kernel->getBundle($bundleName);
                    \assert($bundle instanceof BundleInterface);

                    return new Filesystem($bundle->getPath());
                } catch (\InvalidArgumentException) {
                    // Try plugin loader
                    $bundles = $this->kernel->getPluginLoader()
                        ->getPluginInstances()
                        ->filter(fn (Plugin $plugin) => $plugin->getName() === $bundleName)
                        ->all();

                    if (!empty($bundles)) {
                        $bundle = array_values($bundles)[0];
                        \assert($bundle instanceof BundleInterface);

                        return new Filesystem($bundle->getPath());
                    }
                }
            }
        }

        // Fallback: it's an app
        return $this->sourceResolver->filesystemForAppName($technicalName);
    }
}
