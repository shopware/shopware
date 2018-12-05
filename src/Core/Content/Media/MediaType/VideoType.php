<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Content\Media\Metadata\Type\MetadataType;
use Shopware\Core\Content\Media\Metadata\Type\VideoMetadata;

class VideoType extends MediaType
{
    protected $name = 'VIDEO';

    public function getMetadataType(): MetadataType
    {
        return new VideoMetadata();
    }
}
