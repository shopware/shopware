<?php declare(strict_types=1);

namespace Shopware\Album\Struct;

use Shopware\Media\Struct\MediaBasicCollection;

class AlbumDetailStruct extends AlbumBasicStruct
{
    /**
     * @var MediaBasicCollection
     */
    protected $media;

    public function __construct()
    {
        $this->media = new MediaBasicCollection();
    }

    public function getMedia(): MediaBasicCollection
    {
        return $this->media;
    }

    public function setMedia(MediaBasicCollection $media): void
    {
        $this->media = $media;
    }
}
