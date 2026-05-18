<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileCollection;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Queue\Message\ProcessRunMessage;
use Shopware\Core\Content\ImportExportV2\Queue\Processor\ExportRunProcessor;
use Shopware\Core\Content\ImportExportV2\Queue\Processor\ImportRunProcessor;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Support\FileService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RunService
{
    private const DEFAULT_CHUNK_SIZE = 100;

    private const RUN_TYPE_IMPORT = 'import';

    private const RUN_TYPE_EXPORT = 'export';

    /**
     * @param EntityRepository<ImportExportV2ProfileCollection> $profileRepository
     * @param EntityRepository<ImportExportRunCollection> $runRepository
     */
    public function __construct(
        private readonly EntityRepository $profileRepository,
        private readonly FormatRegistry $formatRegistry,
        private readonly ImportRunProcessor $importRunProcessor,
        private readonly ExportRunProcessor $exportRunProcessor,
        private readonly EntityRepository $runRepository,
        private readonly FileService $fileService,
        private readonly MessageBusInterface $messageBus,
        private readonly Connection $connection
    ) {
    }

    public function startImport(
        ImportExportV2ProfileEntity $profile,
        string $inputFilePath,
        Context $context,
        ?string $inputFileName = null
    ): ImportExportV2RunEntity
    {
        $format = $this->formatRegistry->get($profile->getFormat());

        $file = $this->fileService->createFileFromPath(
            $inputFilePath,
            $inputFileName ?? basename($inputFilePath),
            $format->getMimeType(),
            $context
        );

        $run = $this->createRun(self::RUN_TYPE_IMPORT, $profile->getTechnicalName(), $file, $context);

        $this->messageBus->dispatch(new ProcessRunMessage($context, $run->getId()));

        return $run;
    }

    public function startExport(ImportExportV2ProfileEntity $profile, Context $context): ImportExportV2RunEntity
    {
        $format = $this->formatRegistry->get($profile->getFormat());

        $file = $this->fileService->createFile($profile->getTechnicalName() . '.' . $format->getName(), $format->getMimeType(), '', $context);

        $run = $this->createRun(self::RUN_TYPE_EXPORT, $profile->getTechnicalName(), $file, $context);

        // The profile owns the default export filter. We copy it onto the run
        // so the export keeps using one stable filter payload for its whole
        // lifetime, even if the profile is edited later.
        $run->setExportFilters($profile->getFilters());
        $this->saveRun($run, $context);

        $this->messageBus->dispatch(new ProcessRunMessage($context, $run->getId()));

        return $run;
    }

    public function cancel(string $runId, Context $context): ImportExportV2RunEntity
    {
        $run = $this->getRun($runId, $context);
        if ($run === null) {
            throw ImportExportV2Exception::runNotFound($runId);
        }

        if ($run->getState() === ImportExportV2RunEntity::STATE_QUEUED) {
            $run->markCanceled();
        } elseif ($run->getState() === ImportExportV2RunEntity::STATE_RUNNING) {
            $run->markCancelRequested();
        }

        $this->saveRun($run, $context);

        return $run;
    }

    public function resume(string $runId, Context $context): ImportExportV2RunEntity
    {
        $run = $this->getRun($runId, $context);
        if ($run === null) {
            throw ImportExportV2Exception::runNotFound($runId);
        }

        if (!\in_array($run->getState(), [
            ImportExportV2RunEntity::STATE_FAILED,
            ImportExportV2RunEntity::STATE_CANCELED,
            ImportExportV2RunEntity::STATE_CANCEL_REQUESTED,
        ], true)) {
            throw ImportExportV2Exception::invalidRunState($runId, $run->getState(), 'be resumed');
        }

        $run->markQueued();
        $this->saveRun($run, $context);

        $this->messageBus->dispatch(new ProcessRunMessage($context, $run->getId()));

        return $run;
    }

    public function process(string $runId, Context $context): void
    {
        $run = $this->getRun($runId, $context);
        if ($run === null) {
            throw ImportExportV2Exception::runNotFound($runId);
        }

        if ($run->getState() === ImportExportV2RunEntity::STATE_CANCEL_REQUESTED) {
            $run->markCanceled();
            $file = $this->fileService->getFile($run->getFileId(), $context);
            $this->cleanupImportWorkingCopy($run, $file);
            $this->saveRun($run, $context);

            return;
        }

        $run->markRunning();
        $file = null;

        try {
            $file = $this->fileService->getFile($run->getFileId(), $context);
            if ($file === null) {
                throw ImportExportV2Exception::fileNotFound($run->getFileId());
            }

            $profile = $this->getProfile($run->getProfileName(), $context);

            if ($run->getType() === self::RUN_TYPE_IMPORT) {
                $hasMore = $this->importRunProcessor->processNextChunk($run, $profile, $file, $context);
            } elseif ($run->getType() === self::RUN_TYPE_EXPORT) {
                $hasMore = $this->exportRunProcessor->processNextChunk($run, $profile, $file, $context);

                $this->fileService->saveFile($file, $context);
            } else {
                throw new \RuntimeException(\sprintf('Unsupported run type "%s".', $run->getType()));
            }

            $this->updateRunStateAfterProcessing($run, $runId, $hasMore);

        } catch (\Throwable $exception) {
            $run->markFailed();
            $run->addFailed();
        }

        $this->cleanupImportWorkingCopy($run, $file);

        $this->saveRun($run, $context);

        if ($run->getState() === ImportExportV2RunEntity::STATE_QUEUED) {
            $this->messageBus->dispatch(new ProcessRunMessage($context, $run->getId()));
        }
    }

    public function getRun(string $runId, Context $context): ?ImportExportV2RunEntity
    {
        $entity = $this->runRepository->search(new Criteria([$runId]), $context)->first();

        return $entity instanceof ImportExportV2RunEntity ? $entity : null;
    }

    private function getProfile(string $technicalName, Context $context): ImportExportV2ProfileEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        $entity = $this->profileRepository->search($criteria, $context)->first();
        if (!$entity instanceof ImportExportV2ProfileEntity) {
            throw ImportExportV2Exception::profileNotFound($technicalName);
        }

        return $entity;
    }

    private function createRun(
        string $type,
        string $profileName,
        ImportExportV2FileEntity $file,
        Context $context
    ): ImportExportV2RunEntity {
        $run = new ImportExportV2RunEntity();
        $run->setId(Uuid::randomHex());
        $run->setType($type);
        $run->setProfileName($profileName);
        $run->markQueued();
        $run->setOffset(0);
        $run->setLimit(self::DEFAULT_CHUNK_SIZE);
        $run->setNextByteOffset(null);
        $run->setFileId($file->getId());

        $this->saveRun($run, $context);

        return $run;
    }

    private function saveRun(ImportExportV2RunEntity $run, Context $context): void
    {
        $this->runRepository->upsert([[
            'id' => $run->getId(),
            'type' => $run->getType(),
            'profileName' => $run->getProfileName(),
            'state' => $run->getState(),
            'processed' => $run->getProcessed(),
            'succeeded' => $run->getSucceeded(),
            'failed' => $run->getFailed(),
            'offset' => $run->getOffset(),
            'limit' => $run->getLimit(),
            'nextByteOffset' => $run->getNextByteOffset(),
            'totalRecords' => $run->getTotalRecords(),
            'exportFilters' => $run->getExportFilters(),
            'fileId' => $run->getFileId(),
            'invalidRecordsFileId' => $run->getInvalidRecordsFileId(),
        ]], $context);
    }

    private function isCancelRequested(string $runId): bool
    {
        $state = $this->connection->fetchOne(
            'SELECT `state` FROM `import_export_v2_run` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($runId)]
        );

        return $state === ImportExportV2RunEntity::STATE_CANCEL_REQUESTED;
    }

    private function updateRunStateAfterProcessing(ImportExportV2RunEntity $run, string $runId, bool $hasMore): void
    {
        if ($this->isCancelRequested($runId)) {
            $run->markCanceled();

            return;
        }

        if ($hasMore) {
            $run->markQueued();

            return;
        }

        if ($run->getType() === self::RUN_TYPE_IMPORT && $run->getFailed() > 0) {
            $run->markFailed();

            return;
        }

        $run->markCompleted();
    }

    private function cleanupImportWorkingCopy(ImportExportV2RunEntity $run, ?ImportExportV2FileEntity $file): void
    {
        if ($run->getType() !== self::RUN_TYPE_IMPORT || !$file instanceof ImportExportV2FileEntity) {
            return;
        }

        if (!\in_array($run->getState(), [
            ImportExportV2RunEntity::STATE_COMPLETED,
            ImportExportV2RunEntity::STATE_FAILED,
            ImportExportV2RunEntity::STATE_CANCELED,
        ], true)) {
            return;
        }

        $this->fileService->removeLocalWorkingCopy($file);
    }
}
