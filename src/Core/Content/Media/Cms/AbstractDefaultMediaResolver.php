<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
abstract class AbstractDefaultMediaResolver
{
    abstract public function getDecorated(): AbstractDefaultMediaResolver;

    abstract public function getDefaultCmsMediaEntity(string $mediaAssetFilePath): ?MediaEntity;
}
