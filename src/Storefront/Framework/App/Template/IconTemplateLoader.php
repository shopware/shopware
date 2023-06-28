<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\App\Template;

use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('core')]
class IconTemplateLoader extends AbstractTemplateLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractTemplateLoader $inner,
        private readonly AbstractStorefrontPluginConfigurationFactory $storefrontPluginConfigurationFactory,
        private readonly AbstractAppLoader $appLoader,
        private readonly string $projectDir
    ) {
    }

    public function getTemplatePathsForApp(Manifest $app): array
    {
        $viewPaths = $this->inner->getTemplatePathsForApp($app);

        $resourceDirectory = $this->appLoader->locatePath($app->getPath(), 'Resources');

        if ($resourceDirectory === null) {
            return $viewPaths;
        }

        $relativeAppPath = str_replace($this->projectDir . '/', '', $app->getPath());
        $storefrontConfig = $this->storefrontPluginConfigurationFactory->createFromApp($app->getMetadata()->getName(), $relativeAppPath);

        if (!is_dir($resourceDirectory) || !$storefrontConfig->getIconSets()) {
            return $viewPaths;
        }

        $finder = new Finder();
        $finder->files()
            ->in($resourceDirectory)
            ->name(['*.html.twig', '*.svg'])
            ->path(array_values($storefrontConfig->getIconSets()))
            ->ignoreUnreadableDirs();

        // return file paths relative to Resources/views directory
        $iconPaths = array_values(array_map(static function (\SplFileInfo $file) use ($resourceDirectory): string {
            // remove resource + any leading slashes from pathname
            $resourcePath = ltrim(mb_substr($file->getPathname(), mb_strlen($resourceDirectory)), '/');

            return $resourcePath;
        }, iterator_to_array($finder)));

        return [
            ...array_values($viewPaths),
            ...$iconPaths,
        ];
    }

    public function getTemplateContent(string $path, Manifest $app): string
    {
        if (strrpos($path, '.svg') !== \strlen($path) - 4) {
            return $this->inner->getTemplateContent($path, $app);
        }

        $content = $this->appLoader->loadFile($app->getPath(), 'Resources/' . $path);

        if ($content === null) {
            throw StorefrontFrameworkException::appTemplateFileNotReadable($app->getPath() . '/Resources/' . $path);
        }

        return $content;
    }
}
