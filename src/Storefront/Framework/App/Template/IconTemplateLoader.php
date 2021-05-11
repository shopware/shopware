<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\App\Template;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Symfony\Component\Finder\Finder;

class IconTemplateLoader extends AbstractTemplateLoader
{
    private AbstractTemplateLoader $inner;

    private AbstractStorefrontPluginConfigurationFactory $storefrontPluginConfigurationFactory;

    private string $projectDir;

    public function __construct(
        AbstractTemplateLoader $inner,
        AbstractStorefrontPluginConfigurationFactory $storefrontPluginConfigurationFactory,
        string $projectDir
    ) {
        $this->inner = $inner;
        $this->storefrontPluginConfigurationFactory = $storefrontPluginConfigurationFactory;
        $this->projectDir = $projectDir;
    }

    public function getTemplatePathsForApp(Manifest $app): array
    {
        $viewPaths = $this->inner->getTemplatePathsForApp($app);

        $resourceDirectory = $app->getPath() . '/Resources';

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

            return '../' . $resourcePath;
        }, iterator_to_array($finder)));

        return [
            ...array_values($viewPaths),
            ...$iconPaths,
        ];
    }

    public function getTemplateContent(string $path, Manifest $app): string
    {
        return $this->inner->getTemplateContent($path, $app);
    }
}
