<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Content\Media\Metadata\Type\MetadataType;
use Shopware\Core\Content\Media\Metadata\Type\NoMetadata;

class AudioType extends MediaType
{
    protected $name = 'AUDIO';

    public function getMetadataType(): MetadataType
    {
        return new NoMetadata();
    }
}
