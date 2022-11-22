<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Media\MediaEntity;

/**
 * @package content
 */
abstract class AbstractDefaultMediaResolver
{
    abstract public function getDecorated(): AbstractDefaultMediaResolver;

    abstract public function getDefaultCmsMediaEntity(string $mediaAssetFilePath): ?MediaEntity;
}
