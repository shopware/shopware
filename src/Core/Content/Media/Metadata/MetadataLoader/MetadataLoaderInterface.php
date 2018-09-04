<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: jpietrzyk
 * Date: 26.07.18
 * Time: 08:41
 */

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use Shopware\Core\Content\Media\Metadata\Type\MetadataType;

interface MetadataLoaderInterface
{
    public function extractMetadata(string $filePath): array;

    public function enhanceTypeObject(MetadataType $metadataType, array $rawMetadata): void;
}
