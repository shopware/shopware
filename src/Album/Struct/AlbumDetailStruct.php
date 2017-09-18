<?php declare(strict_types=1);

namespace Shopware\Album\Struct;

use Shopware\Media\Struct\MediaBasicCollection;

class AlbumDetailStruct extends AlbumBasicStruct
{
    /**
     * @var string[]
     */
    protected $mediaUuids = [];

    /**
     * @var MediaBasicCollection
     */
    protected $medias;

    public function __construct()
    {
        $this->medias = new MediaBasicCollection();
    }

    public function getMediaUuids(): array
    {
        return $this->mediaUuids;
    }

    public function setMediaUuids(array $mediaUuids): void
    {
        $this->mediaUuids = $mediaUuids;
    }

    public function getMedias(): MediaBasicCollection
    {
        return $this->medias;
    }

    public function setMedias(MediaBasicCollection $medias): void
    {
        $this->medias = $medias;
    }
}
