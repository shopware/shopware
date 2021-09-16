<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal (flag:FEATURE_NEXT_15998)
 */
abstract class AbstractFileService
{
    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function getDecorated(): AbstractFileService;

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function storeFile(
        Context $context,
        \DateTimeInterface $expireDate,
        ?string $sourcePath,
        ?string $originalFileName,
        string $activity,
        ?string $path = null
    ): ImportExportFileEntity;

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function detectType(UploadedFile $file): string;

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function getWriter(): AbstractWriter;

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function generateFilename(ImportExportProfileEntity $profile): string;

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     */
    abstract public function updateFile(Context $context, string $fileId, array $data): void;
}
