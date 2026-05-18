<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Support\FileService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Writes failed import records into a dedicated invalid-records file by
 * reusing the normal JSON/CSV export writers.
 *
 * Each failed record is the same normalized record payload that moved through
 * the import pipeline, plus one additional `_error` field.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FailedImportRecordExporter
{
    private const ERROR_FIELD = '_error';

    public function __construct(
        private readonly FormatRegistry $formatRegistry,
        private readonly FileService $fileService
    ) {
    }

    /**
     * @param list<ImportExportRecord> $records
     */
    public function append(
        ImportExportV2RunEntity $run,
        ImportExportV2ProfileEntity $profile,
        array $records,
        Context $context
    ): void {
        if ($records === []) {
            return;
        }

        $format = $this->formatRegistry->get($profile->getFormat());

        $writer = $format->getExportWriter();

        $effectiveProfile = $this->createInvalidRecordsProfile($profile);

        $file = $run->getInvalidRecordsFileId() !== null
            ? $this->fileService->getFile($run->getInvalidRecordsFileId(), $context)
            : null;

        if ($file === null) {
            $file = $this->fileService->createFile(
                $profile->getTechnicalName() . '-invalid-records.' . $format->getName(),
                $format->getMimeType(),
                '',
                $context
            );

            $run->setInvalidRecordsFileId($file->getId());
        }

        $writer->append($effectiveProfile, $file, $records);
    }

    private function createInvalidRecordsProfile(ImportExportV2ProfileEntity $profile): ImportExportV2ProfileEntity
    {
        $invalidRecordsProfile = clone $profile;

        $recordPaths = $profile->getRecordPaths();
        if (!\in_array(self::ERROR_FIELD, $recordPaths, true)) {
            $recordPaths[] = self::ERROR_FIELD;
        }

        $invalidRecordsProfile->setRecordPaths($recordPaths);

        if ($profile->getFormat() !== 'csv') {
            return $invalidRecordsProfile;
        }

        $fieldMappings = $profile->getFieldMappings();
        foreach ($fieldMappings as $mapping) {
            $path = $mapping['path'] ?? null;

            if ($path === self::ERROR_FIELD) {
                return $invalidRecordsProfile;
            }
        }

        $fieldMappings[] = [
            'column' => self::ERROR_FIELD,
            'path' => self::ERROR_FIELD,
        ];

        $invalidRecordsProfile->setFieldMappings($fieldMappings);

        return $invalidRecordsProfile;
    }
}
