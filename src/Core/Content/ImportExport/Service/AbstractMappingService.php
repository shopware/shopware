<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal (flag:FEATURE_NEXT_15998)
 */
abstract class AbstractMappingService
{
    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function getDecorated(): AbstractMappingService;

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function createTemplate(Context $context, string $profileId): string;

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function getMappingFromTemplate(
        Context $context,
        UploadedFile $file,
        string $sourceEntity,
        string $delimiter = ';',
        string $enclosure = '"',
        string $escape = '\\'
    ): MappingCollection;
}
