<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('system-settings')]
abstract class AbstractMappingService
{
    abstract public function getDecorated(): AbstractMappingService;

    abstract public function createTemplate(Context $context, string $profileId): string;

    abstract public function getMappingFromTemplate(
        Context $context,
        UploadedFile $file,
        string $sourceEntity,
        string $delimiter = ';',
        string $enclosure = '"',
        string $escape = '\\'
    ): MappingCollection;
}
