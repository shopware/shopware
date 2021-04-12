<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
abstract class AbstractBlockTemplateLoader
{
    abstract public function getTemplateForBlock(CmsExtensions $cmsExtensions, string $blockName): string;

    abstract public function getStylesForBlock(CmsExtensions $cmsExtensions, string $blockName): string;
}
