<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms;

use Shopware\Core\Framework\App\Exception\AppCmsExtensionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('discovery')]
class BlockTemplateLoader extends AbstractBlockTemplateLoader
{
    public function getTemplateForBlock(CmsExtensions $cmsExtensions, string $blockName): string
    {
        $blockDirectory = $this->getBlockDirectory($cmsExtensions, $blockName);

        try {
            $templateFiles = (new Finder())
                ->files()
                ->name('preview.html')
                ->in($blockDirectory);

            foreach ($templateFiles as $templateFile) {
                return $templateFile->getContents();
            }
        } catch (\Exception) {
        }

        throw new AppCmsExtensionException(\sprintf('Preview file for block "%s" is missing', $blockName));
    }

    public function getStylesForBlock(CmsExtensions $cmsExtensions, string $blockName): string
    {
        $blockDirectory = $this->getBlockDirectory($cmsExtensions, $blockName);

        try {
            $styleFiles = (new Finder())
                ->files()
                ->name('styles.css')
                ->in($blockDirectory);

            foreach ($styleFiles as $styleFile) {
                return $styleFile->getContents();
            }
        } catch (\Exception) {
        }

        throw new AppCmsExtensionException(\sprintf('Style file for block "%s" is missing', $blockName));
    }

    private function getBlockDirectory(CmsExtensions $cmsExtensions, string $blockName): string
    {
        $blocksDirectory = Path::join($cmsExtensions->getPath(), 'cms/blocks');
        $blockDirectory = Path::join($blocksDirectory, $blockName);

        // the block name comes from the cms.xml of the app
        if (!Path::isBasePath($blocksDirectory, $blockDirectory)) {
            throw new AppCmsExtensionException(\sprintf('Block "%s" is outside of the cms/blocks directory', $blockName));
        }

        return $blockDirectory;
    }
}
