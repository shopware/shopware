<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('system-settings')]
abstract class AbstractFileService
{
    abstract public function getDecorated(): AbstractFileService;

    abstract public function storeFile(
        Context $context,
        \DateTimeInterface $expireDate,
        ?string $sourcePath,
        ?string $originalFileName,
        string $activity,
        ?string $path = null
    ): ImportExportFileEntity;

    abstract public function detectType(UploadedFile $file): string;

    abstract public function getWriter(): AbstractWriter;

    abstract public function generateFilename(ImportExportProfileEntity $profile): string;

    abstract public function updateFile(Context $context, string $fileId, array $data): void;
}
