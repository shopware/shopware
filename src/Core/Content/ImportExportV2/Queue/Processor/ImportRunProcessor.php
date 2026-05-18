<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Queue\Processor;

use Shopware\Core\Content\ImportExportV2\Event\ImportPayloadBuiltEvent;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Record\ImportPayloadBuilder;
use Shopware\Core\Content\ImportExportV2\Service\FailedImportRecordExporter;
use Shopware\Core\Content\ImportExportV2\Service\ImportEntityMatchResolver;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Service\ImportRecordValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportRunProcessor
{
    public function __construct(
        private readonly FormatRegistry $formatRegistry,
        private readonly ImportPayloadBuilder $importPayloadBuilder,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly ImportRecordValidator $importRecordValidator,
        private readonly ImportEntityMatchResolver $importEntityMatchResolver,
        private readonly FailedImportRecordExporter $failedImportRecordExporter,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return bool Returns true if there are more records to process, false otherwise
     */
    public function processNextChunk(
        ImportExportV2RunEntity $run,
        ImportExportV2ProfileEntity $profile,
        ImportExportV2FileEntity $inputFile,
        Context $context
    ): bool {
        $format = $this->formatRegistry->get($profile->getFormat());

        $chunk = $format->getImportReader()->readChunk(
            $inputFile,
            $profile,
            $run->getOffset(),
            $run->getLimit(),
            $run->getNextByteOffset()
        );

        if ($chunk->totalRecords !== null) {
            $run->setTotalRecords($chunk->totalRecords);
        }

        if ($chunk->records === []) {
            return false;
        }

        $repository = $this->definitionInstanceRegistry->getRepository($profile->getEntity());

        $validatedRecords = [];
        $failedRecords = [];
        $failedWrites = 0;

        foreach ($chunk->records as $record) {
            try {
                // Validation is profile-driven. Records may be a subset of the
                // configured paths, but they must not contain unknown paths or
                // the wrong root entity.
                $validatedRecords[] = $this->importRecordValidator->validate($record, $profile);
            } catch (\Throwable $exception) {
                $failedRecords[] = $this->addImportError($record, $exception->getMessage());

                ++$failedWrites;
            }
        }

        $writePayloads = [];
        $successfulWrites = 0;

        // If the profile matches existing root entities, inject the found id
        // into the mutable record payload before building the final DAL write
        // payload. That turns matching imports into updates.
        $this->importEntityMatchResolver->resolveAll($validatedRecords, $profile, $context);

        if ($validatedRecords !== []) {
            foreach ($validatedRecords as $validatedRecord) {
                $payload = $this->importPayloadBuilder->build($validatedRecord, $profile);

                // Allow further payload modifications via event listeners
                $event = new ImportPayloadBuiltEvent($profile, $validatedRecord, $payload);
                $this->eventDispatcher->dispatch($event);

                $writePayloads[] = [
                    'record' => $validatedRecord,
                    'payload' => $event->payload,
                ];
            }
        }

        if ($writePayloads !== []) {
            try {
                // Happy path: write the whole chunk in one DAL call.
                $repository->upsert(
                    array_map(
                        static fn (array $writePayload): array => $writePayload['payload'],
                        $writePayloads
                    ),
                    $context
                );

                $successfulWrites = \count($writePayloads);
            } catch (\Throwable) {
                // If the batch write fails, retry one by one so valid records
                // can still be imported and invalid ones can be exported with
                // their `_error` message.
                foreach ($writePayloads as $writePayload) {
                    try {
                        $repository->upsert([$writePayload['payload']], $context);

                        ++$successfulWrites;
                    } catch (\Throwable $exception) {
                        $failedRecords[] = $this->addImportError($writePayload['record'], $exception->getMessage());

                        ++$failedWrites;
                    }
                }
            }
        }

        // Failed records are exported into a separate invalid-records file
        // using the same JSON/CSV writer as the main import profile, plus one
        // additional `_error` field.
        $this->failedImportRecordExporter->append($run, $profile, $failedRecords, $context);

        $run->addProcessed(\count($chunk->records));
        $run->addSucceeded($successfulWrites);
        $run->addFailed($failedWrites);
        $run->setOffset($run->getOffset() + \count($chunk->records));
        $run->setNextByteOffset($chunk->nextByteOffset);

        return $chunk->hasMore;
    }

    private function addImportError(ImportExportRecord $record, string $message): ImportExportRecord
    {
        $record->payload['_error'] = $message;

        return $record;
    }
}
