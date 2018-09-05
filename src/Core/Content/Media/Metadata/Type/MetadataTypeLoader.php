<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

abstract class MetadataTypeLoader
{
    abstract public function getValidFileExtensions(): array;

    abstract public function create(): MetadataType;
}
