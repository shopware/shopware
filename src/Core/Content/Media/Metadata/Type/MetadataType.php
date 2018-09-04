<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

use Shopware\Core\Framework\Struct\Struct;

abstract class MetadataType extends Struct
{
    public const UNKNOWN = null;

    abstract public static function getValidFileExtensions(): array;

    abstract public static function create(): MetadataType;

    abstract public function getName(): string;
}
