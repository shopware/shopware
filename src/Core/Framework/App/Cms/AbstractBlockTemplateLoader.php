<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('content')]
abstract class AbstractBlockTemplateLoader
{
    abstract public function getTemplateForBlock(CmsExtensions $cmsExtensions, string $blockName): string;

    abstract public function getStylesForBlock(CmsExtensions $cmsExtensions, string $blockName): string;
}
