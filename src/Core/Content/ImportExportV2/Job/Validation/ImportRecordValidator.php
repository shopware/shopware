<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Validation;

use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Job\Mapping\ImportEntityMapper;
use Shopware\Core\Content\ImportExportV2\Job\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Support\RecordPathExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportRecordValidator
{
    public function __construct(private readonly ImportEntityMapper $importEntityMapper)
    {
    }

    /**
     * @param array<string, mixed> $rawRecord
     */
    public function validate(
        array $rawRecord,
        ImportExportV2ProfileEntity $profile,
        Context $context,
        int $recordIndex
    ): ImportExportRecord {
        if (($rawRecord['entity'] ?? null) !== $profile->getEntity()) {
            throw ImportExportV2Exception::invalidImportRecord($recordIndex, 'Record entity does not match the selected profile.');
        }

        if (!\is_array($rawRecord['identifier'] ?? null)) {
            throw ImportExportV2Exception::invalidImportRecord($recordIndex, 'Record identifier must be an object.');
        }

        if (!\is_array($rawRecord['payload'] ?? null)) {
            throw ImportExportV2Exception::invalidImportRecord($recordIndex, 'Record payload must be an object.');
        }

        $record = new ImportExportRecord(
            (string) $rawRecord['entity'],
            $rawRecord['identifier'],
            $rawRecord['payload']
        );

        // Validation happens in two steps: first the profile decides which paths are allowed,
        // then the mapper checks whether those paths make sense for the actual DAL entity.
        $this->validatePaths($record, $profile, $recordIndex);
        $this->importEntityMapper->validateRecord($record, $profile, $context, $recordIndex);

        return $record;
    }

    private function validatePaths(ImportExportRecord $record, ImportExportV2ProfileEntity $profile, int $recordIndex): void
    {
        $allowedIdentifierPaths = $this->normalizePaths($profile->getIdentifierPaths());
        $identifierPaths = RecordPathExtractor::extract($record->getIdentifier());
        foreach ($identifierPaths as $path) {
            if (!\in_array($path, $allowedIdentifierPaths, true)) {
                throw ImportExportV2Exception::invalidImportRecord(
                    $recordIndex,
                    \sprintf('Identifier path "%s" is not supported by profile "%s".', $path, $profile->getName())
                );
            }
        }

        $allowedPayloadPaths = $this->normalizePaths($profile->getPayloadPaths());
        $payloadPaths = RecordPathExtractor::extract($record->getPayload());
        foreach ($payloadPaths as $path) {
            if (!\in_array($path, $allowedPayloadPaths, true)) {
                throw ImportExportV2Exception::invalidImportRecord(
                    $recordIndex,
                    \sprintf('Payload path "%s" is not supported by profile "%s".', $path, $profile->getName())
                );
            }
        }
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function normalizePaths(array $paths): array
    {
        // CSV imports can expand list paths into numbered items, so we normalize numeric indexes
        // back to "*" before comparing them against the profile definition.
        $normalized = array_map(
            static fn (string $path): string => preg_replace('/\.\d+(?=\.|$)/', '.*', $path) ?? $path,
            $paths
        );

        return array_values(array_unique($normalized));
    }
}
