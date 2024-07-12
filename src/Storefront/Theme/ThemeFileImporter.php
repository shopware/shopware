<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Symfony\Component\Finder\Finder;

/**
 * @deprecated tag:v6.7.0 Will be removed.
 */
#[Package('storefront')]
class ThemeFileImporter implements ThemeFileImporterInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly string $projectDir)
    {
    }

    public function fileExists(string $filePath): bool
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        return file_exists($filePath) && !is_dir($filePath);
    }

    public function getRealPath(string $filePath): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        if ($filePath[0] === '/' || !file_exists($this->projectDir . '/' . $filePath)) {
            return $filePath;
        }

        return $this->projectDir . '/' . $filePath;
    }

    public function getConcatenableStylePath(File $file, StorefrontPluginConfiguration $configuration): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        return '@import \'' . $file->getFilepath() . '\';' . \PHP_EOL;
    }

    public function getConcatenableScriptPath(File $file, StorefrontPluginConfiguration $configuration): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        return file_get_contents($file->getFilepath()) . \PHP_EOL;
    }

    public function getCopyBatchInputsForAssets(string $assetPath, string $outputPath, StorefrontPluginConfiguration $configuration): array
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'));

        if (!is_dir($assetPath)) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                sprintf('Unable to find asset. Path: "%s"', $assetPath)
            );
        }

        $files = (new Finder())->files()->in($assetPath);
        $assets = [];

        foreach ($files as $file) {
            $relativePathname = $file->getRelativePathname();
            $assetDir = basename($assetPath);

            $assets[] = new CopyBatchInput(
                $assetPath . \DIRECTORY_SEPARATOR . $relativePathname,
                [
                    $outputPath . \DIRECTORY_SEPARATOR . $assetDir . \DIRECTORY_SEPARATOR . $relativePathname,
                ]
            );
        }

        return $assets;
    }
}
