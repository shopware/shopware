<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @Decoratable
 */
interface ThemeFileImporterInterface
{
    public function fileExists(string $filePath): bool;

    public function getRealPath(string $filePath): string;

    public function getConcatenableStylePath(File $file, StorefrontPluginConfiguration $configuration): string;

    public function getConcatenableScriptPath(File $file, StorefrontPluginConfiguration $configuration): string;

    /**
     * @return CopyBatchInput[]
     */
    public function getCopyBatchInputsForAssets(string $assetPath, string $outputPath, StorefrontPluginConfiguration $configuration): array;
}
