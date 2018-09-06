<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class NoMetadata extends MetadataType
{
    public static function getValidFileExtensions(): array
    {
        return [];
    }

    public static function create(): MetadataType
    {
        return new self();
    }

    public function getName(): string
    {
        return 'unknown';
    }
}
