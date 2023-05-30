<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Exception\RequiredByUserException;
use Shopware\Core\Content\ImportExport\Processing\Mapping\CriteriaBuilder;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Service\AbstractFileService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteCommandExceptionEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('system-settings')]
class ImportExport
{
    private const PART_FILE_SUFFIX = '.offset_';

    private ?int $total = null;

    /**
     * @var WriteCommand[]|null
     */
    private ?array $failedWriteCommands = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly ImportExportService $importExportService,
        private readonly ImportExportLogEntity $logEntity,
        private readonly FilesystemOperator $filesystem,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly EntityRepository $repository,
        private readonly AbstractPipe $pipe,
        private readonly AbstractReader $reader,
        private readonly AbstractWriter $writer,
        private readonly AbstractFileService $fileService,
        private readonly int $importLimit = 250,
        private readonly int $exportLimit = 250
    ) {
    }

    public function import(Context $context, int $offset = 0): Progress
    {
        $progress = $this->importExportService->getProgress($this->logEntity->getId(), $offset);
        $progress->setTotal($this->logEntity->getFile()->getSize());

        if ($progress->isFinished()) {
            return $progress;
        }

        $processed = 0;

        $path = $this->logEntity->getFile()->getPath();
        $progress->setTotal($this->filesystem->fileSize($path));
        $invalidRecordsProgress = null;

        $failedRecords = [];

        $resource = $this->filesystem->readStream($path);
        $config = Config::fromLog($this->logEntity);
        $overallResults = $this->logEntity->getResult();

        $this->eventDispatcher->addListener(WriteCommandExceptionEvent::class, $this->onWriteException(...));

        $createEntities = $config->get('createEntities') ?? true;
        $updateEntities = $config->get('updateEntities') ?? true;

        foreach ($this->reader->read($config, $resource, $offset) as $row) {
            $event = new ImportExportBeforeImportRowEvent($row, $config, $context);
            $this->eventDispatcher->dispatch($event);
            $row = $event->getRow();

            // empty csv lines were already skipped by the reader.
            // defaults are added to the raw csv row
            $this->addUserDefaults($row, $config);

            $record = [];
            foreach ($this->pipe->out($config, $row) as $key => $value) {
                $record[$key] = $value;
            }

            if (empty($record)) {
                continue;
            }

            $result = null;
            $this->failedWriteCommands = null;

            if ($this->logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_DRYRUN) {
                $this->connection->setNestTransactionsWithSavepoints(true);
                $this->connection->beginTransaction();
            }

            try {
                if (isset($record['_error']) && $record['_error'] instanceof \Throwable) {
                    throw $record['_error'];
                }

                // ensure that the raw csv row has all the fields, which are marked as required by the user.
                $this->ensureUserRequiredFields($row, $config);

                $record = $this->ensurePrimaryKeys($record);

                $event = new ImportExportBeforeImportRecordEvent($record, $row, $config, $context);
                $this->eventDispatcher->dispatch($event);

                $record = $event->getRecord();

                if ($createEntities === true && $updateEntities === false) {
                    $result = $this->repository->create([$record], $context);
                } elseif ($createEntities === false && $updateEntities === true) {
                    $result = $this->repository->update([$record], $context);
                } else {
                    // expect that both create and update are true -> upsert
                    // both false isn't possible via admin (but still results in an upsert)
                    $result = $this->repository->upsert([$record], $context);
                }

                $progress->addProcessedRecords(1);

                $afterRecord = new ImportExportAfterImportRecordEvent($result, $record, $row, $config, $context);
                $this->eventDispatcher->dispatch($afterRecord);
            } catch (\Throwable $exception) {
                $event = new ImportExportExceptionImportRecordEvent($exception, $record, $row, $config, $context);
                $this->eventDispatcher->dispatch($event);

                $exception = $event->getException();

                if ($exception) {
                    $record['_error'] = mb_convert_encoding($exception->getMessage(), 'UTF-8', 'UTF-8');
                    $failedRecords[] = $record;
                }
            }

            if ($this->logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_DRYRUN) {
                $this->connection->rollBack();
            }

            $this->importExportService->saveProgress($progress);

            $overallResults = $this->logResults($overallResults, $result, $this->repository->getDefinition()->getEntityName());

            ++$processed;
            if ($this->importLimit > 0 && $processed >= $this->importLimit) {
                break;
            }
        }
        $progress->setOffset($this->reader->getOffset());

        $this->eventDispatcher->removeListener(WriteCommandExceptionEvent::class, $this->onWriteException(...));

        if (!empty($failedRecords)) {
            $invalidRecordsProgress = $this->exportInvalid($context, $failedRecords);
            $progress->setInvalidRecordsLogId($invalidRecordsProgress->getLogId());
        }

        // importing the file is complete
        if ($this->reader->getOffset() === $this->filesystem->fileSize($path)) {
            if ($this->logEntity->getInvalidRecordsLog() !== null) {
                $invalidLog = $this->logEntity->getInvalidRecordsLog();
                $invalidRecordsProgress ??= $this->importExportService->getProgress($invalidLog->getId(), $invalidLog->getRecords());

                // complete invalid records export
                $this->mergePartFiles($this->logEntity->getInvalidRecordsLog(), $invalidRecordsProgress);

                $invalidRecordsProgress->setState(Progress::STATE_SUCCEEDED);
                $this->importExportService->saveProgress($invalidRecordsProgress);
            }

            $progress->setState($invalidRecordsProgress === null ? Progress::STATE_SUCCEEDED : Progress::STATE_FAILED);
        }

        $this->importExportService->saveProgress($progress, $overallResults);

        return $progress;
    }

    public function export(Context $context, ?Criteria $criteria = null, int $offset = 0): Progress
    {
        $progress = $this->importExportService->getProgress($this->logEntity->getId(), $offset);

        if ($progress->isFinished()) {
            return $progress;
        }

        $config = Config::fromLog($this->logEntity);
        $criteriaBuilder = new CriteriaBuilder($this->repository->getDefinition());

        $criteria = $criteria === null ? new Criteria() : clone $criteria;
        $criteriaBuilder->enrichCriteria($config, $criteria);

        $enrichEvent = new EnrichExportCriteriaEvent($criteria, $this->logEntity);
        $this->eventDispatcher->dispatch($enrichEvent);

        if ($criteria->getSorting() === []) {
            // default sorting
            $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        }
        $criteria->addSorting(new FieldSorting('id'));

        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $criteria->setLimit($this->exportLimit <= 0 ? 250 : $this->exportLimit);
        $fullExport = $this->exportLimit <= 0;

        $targetFile = $this->getPartFilePath($this->logEntity->getFile()->getPath(), $offset);

        do {
            $result = $this->repository->search($criteria, $context);
            if ($this->total === null) {
                $this->total = $result->getTotal();
                $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
            }

            $entities = $result->getEntities();
            if (\count($entities) === 0) {
                // this can happen if entities are deleted while we export
                $progress->setTotal($progress->getOffset());

                break;
            }

            $progress = $this->exportChunk($config, $entities, $progress, $targetFile);

            $criteria->setOffset($criteria->getOffset() + $criteria->getLimit());
        } while ($fullExport && $progress->getOffset() < $progress->getTotal());

        if ($progress->getTotal() > $progress->getOffset()) {
            return $progress;
        }

        $this->writer->finish($config, $targetFile);

        return $this->mergePartFiles($this->logEntity, $progress);
    }

    public function abort(): void
    {
        $invalidLog = $this->logEntity->getInvalidRecordsLog();
        if ($invalidLog !== null) {
            $invalidRecordsProgress = $this->importExportService->getProgress($invalidLog->getId(), $invalidLog->getRecords());

            // complete invalid records export
            $this->mergePartFiles($invalidLog, $invalidRecordsProgress);

            $invalidRecordsProgress->setState(Progress::STATE_SUCCEEDED);
            $this->importExportService->saveProgress($invalidRecordsProgress);
        }
    }

    public function getLogEntity(): ImportExportLogEntity
    {
        return $this->logEntity;
    }

    public function onWriteException(WriteCommandExceptionEvent $event): void
    {
        $this->failedWriteCommands = $event->getCommands();
    }

    private function getPartFilePath(string $targetPath, int $offset): string
    {
        return $targetPath . self::PART_FILE_SUFFIX . $offset;
    }

    /**
     * flysystem does not support appending to existing files. Therefore we need to export multiple files and merge them
     * into the complete export file at the end.
     */
    private function mergePartFiles(ImportExportLogEntity $logEntity, Progress $progress): Progress
    {
        $progress->setState(Progress::STATE_MERGING_FILES);
        $this->importExportService->saveProgress($progress);

        $tmpFile = tempnam(sys_get_temp_dir(), '');
        /** @var resource $tmp */
        $tmp = fopen($tmpFile, 'w+b');

        $target = $logEntity->getFile()->getPath();

        $dir = \dirname($target);

        $partFilePrefix = $target . self::PART_FILE_SUFFIX;

        $partFiles = [];

        foreach ($this->filesystem->listContents($dir) as $meta) {
            if ($meta->type() !== 'file'
                || $meta->path() === $target
                || !str_starts_with($meta->path(), $partFilePrefix)) {
                continue;
            }

            $partFiles[] = $meta->path();
        }

        // sort by offset
        natsort($partFiles);

        // concatenate all part files into a temporary file
        foreach ($partFiles as $partFile) {
            $stream = $this->filesystem->readStream($partFile);
            if (stream_copy_to_stream($stream, $tmp) === false) {
                throw new ProcessingException('Failed to merge files');
            }
        }

        // copy final file into filesystem
        $this->filesystem->writeStream($target, $tmp);

        if (\is_resource($tmp)) {
            fclose($tmp);
        }
        unlink($tmpFile);

        foreach ($partFiles as $p) {
            $this->filesystem->delete($p);
        }

        $progress->setState(Progress::STATE_SUCCEEDED);
        $this->importExportService->saveProgress($progress);

        $fileId = $logEntity->getFileId();
        if ($fileId === null) {
            throw new ProcessingException('log does not have a file id');
        }

        $this->fileService->updateFile(
            Context::createDefaultContext(),
            $fileId,
            ['size' => $this->filesystem->fileSize($target)]
        );

        return $progress;
    }

    private function exportChunk(Config $config, iterable $records, Progress $progress, string $targetFile): Progress
    {
        $exportedRecords = 0;
        $offset = $progress->getOffset();
        /** @var Entity|array $originalRecord */
        foreach ($records as $originalRecord) {
            $originalRecord = $originalRecord instanceof Entity
                ? $originalRecord->jsonSerialize()
                : $originalRecord;

            $record = [];
            foreach ($this->pipe->in($config, $originalRecord) as $key => $value) {
                $record[$key] = $value;
            }

            if ($record !== []) {
                $event = new ImportExportBeforeExportRecordEvent($config, $record, $originalRecord);
                $this->eventDispatcher->dispatch($event);

                $record = $event->getRecord();

                $this->writer->append($config, $record, $offset);
                ++$exportedRecords;
            }

            ++$offset;
        }

        $this->writer->flush($config, $targetFile);

        $progress->setState(Progress::STATE_PROGRESS);
        $progress->setOffset($offset);
        $progress->setTotal($this->total);
        $progress->addProcessedRecords($exportedRecords);

        $this->importExportService->saveProgress($progress);

        return $progress;
    }

    /**
     * In case we failed to import some invalid records, we export them as a new csv with the same format and
     * an additional _error column.
     */
    private function exportInvalid(Context $context, array $failedRecords): Progress
    {
        // created a invalid records export if it doesn't exist
        if (!$this->logEntity->getInvalidRecordsLogId()) {
            $pathInfo = pathinfo($this->logEntity->getFile()->getOriginalName());
            $newName = $pathInfo['filename'] . '_failed.' . ($pathInfo['extension'] ?? '');

            $newPath = $this->logEntity->getFile()->getPath() . '_invalid';

            $config = $this->logEntity->getConfig();
            $config['mapping'][] = [
                'key' => '_error',
                'mappedKey' => '_error',
            ];
            $config = new Config($config['mapping'], $config['parameters'] ?? [], $config['updateBy'] ?? []);

            $failedImportLogEntity = $this->importExportService->prepareExport(
                $context,
                $this->logEntity->getProfileId(),
                $this->logEntity->getFile()->getExpireDate(),
                $newName,
                $config->jsonSerialize(),
                $newPath,
                ImportExportLogEntity::ACTIVITY_INVALID_RECORDS_EXPORT
            );

            $this->logEntity->setInvalidRecordsLog($failedImportLogEntity);
            $this->logEntity->setInvalidRecordsLogId($failedImportLogEntity->getId());
        }

        $failedImportLogEntity = $this->logEntity->getInvalidRecordsLog();
        $config = Config::fromLog($failedImportLogEntity);

        $offset = $failedImportLogEntity->getRecords();

        $targetFile = $this->getPartFilePath($failedImportLogEntity->getFile()->getPath(), $offset);

        $progress = $this->importExportService->getProgress($failedImportLogEntity->getId(), $offset);

        $progress = $this->exportChunk(
            $config,
            $failedRecords,
            $progress,
            $targetFile
        );

        return $progress;
    }

    private function ensurePrimaryKeys(array $data): array
    {
        foreach ($this->repository->getDefinition()->getPrimaryKeys() as $primaryKey) {
            if (!($primaryKey instanceof IdField)) {
                continue;
            }

            if (!isset($data[$primaryKey->getPropertyName()])) {
                $data[$primaryKey->getPropertyName()] = Uuid::randomHex();
            }
        }

        return $data;
    }

    private function addUserDefaults(array &$row, Config $config): void
    {
        $mappings = $config->getMapping()->getElements();

        foreach ($mappings as $mapping) {
            $csvKey = $mapping->getMappedKey();

            if (!$mapping->isUseDefaultValue()) {
                continue;
            }

            if (!\array_key_exists($csvKey, $row) || empty($row[$csvKey])) {
                $row[$csvKey] = $mapping->getDefaultValue();
            }
        }
    }

    private function ensureUserRequiredFields(array &$row, Config $config): void
    {
        $mappings = $config->getMapping()->getElements();

        foreach ($mappings as $mapping) {
            $csvKey = $mapping->getMappedKey();

            if (!$mapping->isRequiredByUser()) {
                continue;
            }

            if (!\array_key_exists($csvKey, $row) || empty($row[$csvKey])) {
                throw new RequiredByUserException($csvKey);
            }
        }
    }

    /**
     * @param array<string, mixed> $overallResults
     *
     * @return array<string, mixed>
     */
    private function logResults(
        array $overallResults,
        ?EntityWrittenContainerEvent $result,
        string $entityName
    ): array {
        $defaultTemplate = [
            sprintf('%sSkip', EntityWriteResult::OPERATION_INSERT) => 0,
            sprintf('%sSkip', EntityWriteResult::OPERATION_UPDATE) => 0,
            sprintf('%sError', EntityWriteResult::OPERATION_INSERT) => 0,
            sprintf('%sError', EntityWriteResult::OPERATION_UPDATE) => 0,
            'otherError' => 0,
            EntityWriteResult::OPERATION_INSERT => 0,
            EntityWriteResult::OPERATION_UPDATE => 0,
        ];

        if (!$result && !$this->failedWriteCommands) {
            $entityResult = $overallResults[$entityName] ?? $defaultTemplate;
            ++$entityResult['otherError'];
            $overallResults[$entityName] = $entityResult;

            return $overallResults;
        }

        if (!$result && $this->failedWriteCommands) {
            foreach ($this->failedWriteCommands as $writeCommand) {
                if (!$writeCommand instanceof WriteCommand) {
                    continue;
                }

                $entityName = $writeCommand->getDefinition()->getEntityName();
                $entityResult = $overallResults[$entityName] ?? $defaultTemplate;
                $operation = $writeCommand->getEntityExistence()->exists()
                    ? EntityWriteResult::OPERATION_UPDATE
                    : EntityWriteResult::OPERATION_INSERT;
                $type = $writeCommand->isFailed() ? 'Error' : 'Skip';
                ++$entityResult[sprintf('%s%s', $operation, $type)];
                $overallResults[$entityName] = $entityResult;
            }

            return $overallResults;
        }

        if (!$result || !$result->getEvents()) {
            return $overallResults;
        }

        foreach ($result->getEvents() as $event) {
            if (!$event instanceof EntityWrittenEvent) {
                continue;
            }

            foreach ($event->getWriteResults() as $writeResult) {
                $entityResult = $overallResults[$writeResult->getEntityName()] ?? $defaultTemplate;
                ++$entityResult[$writeResult->getOperation()];
                $overallResults[$writeResult->getEntityName()] = $entityResult;
            }
        }

        return $overallResults;
    }
}
