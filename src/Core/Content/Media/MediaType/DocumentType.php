<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Content\Media\Metadata\Type\DocumentMetadata;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;

class DocumentType extends MediaType
{
    protected $name = 'DOCUMENT';

    public function getMetadataType(): MetadataType
    {
        return new DocumentMetadata();
    }
}
