<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Symfony\Component\Finder\Finder;

/**
 * @Decoratable
 */
class ThemeFileImporter implements ThemeFileImporterInterface
{
    public function fileExists(string $filePath): bool
    {
        return file_exists($filePath) && !is_dir($filePath);
    }

    public function getRealPath(string $filePath): string
    {
        return $filePath;
    }

    public function getConcatenableStylePath(File $file, StorefrontPluginConfiguration $configuration): string
    {
        return '@import \'' . $file->getFilepath() . '\';' . \PHP_EOL;
    }

    public function getConcatenableScriptPath(File $file, StorefrontPluginConfiguration $configuration): string
    {
        return file_get_contents($file->getFilepath()) . \PHP_EOL;
    }

    public function getCopyBatchInputsForAssets(string $assetPath, string $outputPath, StorefrontPluginConfiguration $configuration): array
    {
        if (!is_dir($assetPath)) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                sprintf('Unable to find asset. Path: "%s"', $assetPath)
            );
        }

        $finder = new Finder();
        $files = $finder->files()->in($assetPath);
        $assets = [];

        foreach ($files as $file) {
            $relativePathname = $file->getRelativePathname();
            $assetDir = basename($assetPath);

            if (Feature::isActive('FEATURE_NEXT_14699')) {
                $assets[] = new CopyBatchInput(
                    $assetPath . \DIRECTORY_SEPARATOR . $relativePathname,
                    [
                        $outputPath . \DIRECTORY_SEPARATOR . $assetDir . \DIRECTORY_SEPARATOR . $relativePathname,
                    ]
                );
            } else {
                $assets[] = new CopyBatchInput(
                    $assetPath . \DIRECTORY_SEPARATOR . $relativePathname,
                    [
                        'bundles' . \DIRECTORY_SEPARATOR . mb_strtolower($configuration->getTechnicalName()) . \DIRECTORY_SEPARATOR . $assetDir . \DIRECTORY_SEPARATOR . $relativePathname,
                        $outputPath . \DIRECTORY_SEPARATOR . $assetDir . \DIRECTORY_SEPARATOR . $relativePathname,
                    ]
                );
            }
        }

        return $assets;
    }
}
