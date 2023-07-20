<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Implementation;

use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Path\Contract\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Path\Contract\Struct\ThumbnailLocationStruct;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
class PhysicalFilenamePathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'physical_file_name';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        $media = $location instanceof ThumbnailLocationStruct ? $location->media : $location;

        $timestamp = $media->uploadedAt ? $media->uploadedAt->getTimestamp() . '/' : '';

        return $timestamp . $media->fileName;
    }
}
