<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;

class Md5PathnameStrategy extends AbstractPathNameStrategy
{
    /**
     * @var array
     */
    private $blacklist = [
        'ad' => 'g0',
    ];

    public function generatePathHash(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string
    {
        $md5hash = md5($media->getFileName());

        $md5hashSlices = \array_slice(str_split($md5hash, 2), 0, 3);
        $md5hashSlices = array_map(
            function ($slice) {
                return array_key_exists($slice, $this->blacklist) ? $this->blacklist[$slice] : $slice;
            },
            $md5hashSlices
        );

        return implode('/', $md5hashSlices);
    }

    public function getName(): string
    {
        return 'md5';
    }
}
