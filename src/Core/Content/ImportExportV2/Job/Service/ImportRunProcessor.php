<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Service;

use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Job\Artifact\ImportExportV2ArtifactEntity;
use Shopware\Core\Content\ImportExportV2\Job\Mapping\ImportEntityMapper;
use Shopware\Core\Content\ImportExportV2\Job\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Job\Validation\ImportRecordValidator;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportRunProcessor
{
    public function __construct(
        private readonly FormatRegistry $formatRegistry,
        private readonly ImportEntityMapper $importEntityMapper,
        private readonly ImportRecordValidator $importRecordValidator,
        private readonly EntityWriter $entityWriter
    ) {
    }

    public function processNextChunk(
        ImportExportV2RunEntity $run,
        ImportExportV2ProfileEntity $profile,
        ImportExportV2ArtifactEntity $inputArtifact,
        Context $context
    ): bool {
        $format = $this->formatRegistry->get($profile->getFormat());
        $chunk = $format->getImportReader()->readChunk(
            $inputArtifact->getContents(),
            $profile,
            $run->getOffset(),
            $run->getChunkSize()
        );

        $run->setTotalRecords($chunk['totalRecords']);
        if ($chunk['records'] === []) {
            return false;
        }

        $writeEntries = [];
        $recordFailures = [];
        foreach ($chunk['records'] as $chunkIndex => $record) {
            $recordIndex = $run->getOffset() + $chunkIndex;

            try {
                \assert(\is_array($record));
                // Readers only translate the file format. Validation and DAL mapping happen afterwards
                // so every format goes through the same import rules.
                $importExportRecord = $this->importRecordValidator->validate($record, $profile, $context, $recordIndex);
                $writeEntries[] = [
                    'recordIndex' => $recordIndex,
                    'payload' => $this->importEntityMapper->buildWritePayload($importExportRecord, $profile, $context),
                ];
            } catch (\Throwable $exception) {
                $recordFailures[] = [
                    'recordIndex' => $recordIndex,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if ($writeEntries !== []) {
            // We collect all valid records first and write them in one DAL upsert for the spike's batch-first behavior.
            $payloads = array_values(array_map(static fn (array $entry): array => $entry['payload'], $writeEntries));
            $this->entityWriter->upsert($profile->getEntity(), $payloads, $context);
        }

        $run->addProcessed(\count($chunk['records']));
        $run->addSucceeded(\count($writeEntries));
        foreach ($recordFailures as $recordFailure) {
            $run->addFailure($recordFailure['recordIndex'], $recordFailure['message']);
        }

        $run->setOffset($chunk['nextOffset']);

        return $chunk['hasMore'];
    }
}
