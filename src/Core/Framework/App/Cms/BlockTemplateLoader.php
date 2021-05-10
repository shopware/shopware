<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms;

use Shopware\Core\Framework\App\Exception\AppCmsExtensionException;
use Symfony\Component\Finder\Finder;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class BlockTemplateLoader extends AbstractBlockTemplateLoader
{
    public function getTemplateForBlock(CmsExtensions $cmsExtensions, string $blockName): string
    {
        try {
            $templateFiles = (new Finder())
                ->files()
                ->name('preview.html')
                ->in(sprintf('%s/cms/blocks/%s', $cmsExtensions->getPath(), $blockName));

            foreach ($templateFiles as $templateFile) {
                return $templateFile->getContents();
            }
        } catch (\Exception $e) {
        }

        throw new AppCmsExtensionException(sprintf('Preview file for block "%s" is missing', $blockName));
    }

    public function getStylesForBlock(CmsExtensions $cmsExtensions, string $blockName): string
    {
        try {
            $styleFiles = (new Finder())
                ->files()
                ->name('styles.css')
                ->in(sprintf('%s/cms/blocks/%s', $cmsExtensions->getPath(), $blockName));

            foreach ($styleFiles as $styleFile) {
                return $styleFile->getContents();
            }
        } catch (\Exception $e) {
        }

        throw new AppCmsExtensionException(sprintf('Style file for block "%s" is missing', $blockName));
    }
}
