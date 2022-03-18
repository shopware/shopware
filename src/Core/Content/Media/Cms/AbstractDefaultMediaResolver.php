<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Media\MediaEntity;

abstract class AbstractDefaultMediaResolver
{
    public const CMS_DEFAULT_ASSETS_PATH = 'assets/default/cms/';

    abstract public function getDecorated(): AbstractDefaultMediaResolver;

    abstract public function getDefaultCmsMediaEntity(string $cmsAssetFileName): ?MediaEntity;
}
