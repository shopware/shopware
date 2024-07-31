<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Kernel;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[Package('storefront')]
class ThemeFilesystemResolver
{
    /**
     * @var list<string>
     */
    private readonly array $bundleNames;

    public function __construct(
        private readonly SourceResolver $sourceResolver,
        private readonly string $projectDir,
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
        if (!\in_array($configuration->getTechnicalName(), $this->bundleNames, true)) {
            // it's an app
            return $this->sourceResolver->filesystemForAppName($configuration->getTechnicalName());
        }

        // For themes, we get basePath with Resources and for Plugins without, so we always remove and add it again
        $path = str_replace('/Resources', '', $configuration->getBasePath());

        return new Filesystem($this->projectDir . '/' . $path);
    }

    public function makePathRelativeToFilesystemRoot(string $path, StorefrontPluginConfiguration $configuration): string
    {
        $basePath = str_replace('/Resources', '', $configuration->getBasePath());

        return str_replace($basePath, '', $path);
    }
}
