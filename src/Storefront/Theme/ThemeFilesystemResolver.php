<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
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
        private readonly Kernel $kernel
    ) {
        $this->bundleNames = array_keys($this->kernel->getBundles());
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
