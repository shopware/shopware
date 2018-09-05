<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class DocumentMetadataLoader extends MetadataTypeLoader
{

    public function getValidFileExtensions(): array
    {
        return [
            'pdf',
            'doc',
            'docx',
        ];
    }

    public function create(): MetadataType
    {
        return new DocumentMetadata();
    }

}
