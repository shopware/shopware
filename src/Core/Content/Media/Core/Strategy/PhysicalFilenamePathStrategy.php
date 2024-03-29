<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Strategy;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
#[Package('buyers-experience')]
class PhysicalFilenamePathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'physical_filename';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        $media = $location instanceof ThumbnailLocationStruct ? $location->media : $location;

        $timestamp = $media->uploadedAt ? $media->uploadedAt->getTimestamp() . '/' : '';

        return $timestamp . $media->fileName;
    }
}
