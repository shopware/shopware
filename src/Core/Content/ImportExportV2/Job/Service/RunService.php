<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Job\Artifact\ImportExportV2ArtifactCollection;
use Shopware\Core\Content\ImportExportV2\Job\Artifact\ImportExportV2ArtifactEntity;
use Shopware\Core\Content\ImportExportV2\Job\Message\ProcessRunMessage;
use Shopware\Core\Content\ImportExportV2\Job\Request\ExportRunRequest;
use Shopware\Core\Content\ImportExportV2\Job\Request\ImportRunRequest;
use Shopware\Core\Content\ImportExportV2\Job\Run\ImportExportV2RunCollection;
use Shopware\Core\Content\ImportExportV2\Job\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileCollection;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Defaults;
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

    private const LEASE_MINUTES = 5;

    /**
     * @param EntityRepository<ImportExportV2ProfileCollection> $profileRepository
     * @param EntityRepository<ImportExportV2RunCollection> $runRepository
     * @param EntityRepository<ImportExportV2ArtifactCollection> $artifactRepository
     */
    public function __construct(
        private readonly EntityRepository $profileRepository,
        private readonly FormatRegistry $formatRegistry,
        private readonly ImportRunProcessor $importRunProcessor,
        private readonly ExportRunProcessor $exportRunProcessor,
        private readonly EntityRepository $runRepository,
        private readonly EntityRepository $artifactRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly Connection $connection
    ) {
    }

    public function startImport(ImportRunRequest $request, Context $context): ImportExportV2RunEntity
    {
        $profile = $this->getProfile($request->getProfileName(), $context);
        $format = $this->formatRegistry->get($profile->getFormat());
        $run = $this->createRun('import', $profile->getName(), $request->getOptions(), $context);
        // Input contents are persisted as an artifact so the queued worker can process the run later.
        $inputArtifact = $this->createArtifact(
            $request->getInputFileName() ?? $profile->getFormatFileName($format->getName()),
            $request->getInputMimeType() ?? $format->getMimeType(),
            $request->getInputContents(),
            $context
        );
        $run->setInputArtifactId($inputArtifact->getId());
        $this->saveRun($run, $context);

        $this->messageBus->dispatch(new ProcessRunMessage($context, $run->getId()));

        return $run;
    }

    public function startExport(ExportRunRequest $request, Context $context): ImportExportV2RunEntity
    {
        $profile = $this->getProfile($request->getProfileName(), $context);
        $format = $this->formatRegistry->get($profile->getFormat());
        $run = $this->createRun('export', $profile->getName(), $request->getOptions(), $context);
        $outputArtifact = $this->createArtifact(
            $profile->getFormatFileName($format->getName()),
            $format->getMimeType(),
            $format->getExportWriter()->initialize($profile),
            $context
        );
        $run->setOutputArtifactId($outputArtifact->getId());
        $run->setRecordIds($request->getRecordIds());
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
            $run->clearLease();
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
        $run->clearLease();
        $run->setLastError(null);
        $this->saveRun($run, $context);

        $this->messageBus->dispatch(new ProcessRunMessage($context, $run->getId()));

        return $run;
    }

    public function process(string $runId, Context $context): void
    {
        $processingToken = Uuid::randomHex();
        if (!$this->claimLease($runId, $processingToken)) {
            return;
        }

        $run = $this->getRun($runId, $context);
        if ($run === null) {
            throw ImportExportV2Exception::runNotFound($runId);
        }

        $run->setProcessingToken($processingToken);
        // From here on the persisted run entity is the single source of truth for status and counters.
        if ($run->getState() === ImportExportV2RunEntity::STATE_CANCEL_REQUESTED) {
            $run->markCanceled();
            $run->clearLease();
            $this->saveRun($run, $context);

            return;
        }

        $run->markRunning();

        try {
            if ($run->getType() === 'import') {
                $artifactId = $run->getInputArtifactId();
                if ($artifactId === null) {
                    throw ImportExportV2Exception::artifactNotFound('missing-input-artifact');
                }

                $artifact = $this->getArtifact($artifactId, $context);
                if ($artifact === null) {
                    throw ImportExportV2Exception::artifactNotFound($artifactId);
                }

                $profile = $this->getProfile($run->getProfileName(), $context);
                $hasMore = $this->importRunProcessor->processNextChunk($run, $profile, $artifact, $context);
            } elseif ($run->getType() === 'export') {
                $artifactId = $run->getOutputArtifactId();
                if ($artifactId === null) {
                    throw ImportExportV2Exception::artifactNotFound('missing-output-artifact');
                }

                $artifact = $this->getArtifact($artifactId, $context);
                if ($artifact === null) {
                    throw ImportExportV2Exception::artifactNotFound($artifactId);
                }

                $profile = $this->getProfile($run->getProfileName(), $context);
                $hasMore = $this->exportRunProcessor->processNextChunk($run, $profile, $artifact, $run->getRecordIds(), $context);
                $this->saveArtifact($artifact, $context);
            } else {
                throw new \RuntimeException(\sprintf('Unsupported run type "%s".', $run->getType()));
            }

            if ($this->isCancelRequested($runId)) {
                $run->markCanceled();
            } elseif ($hasMore) {
                $run->markQueued();
            } else {
                $run->markCompleted();
            }

            $run->setLastError(null);
        } catch (\Throwable $exception) {
            $run->markFailed();
            $run->setLastError($exception->getMessage());
            $run->addFailure(-1, $exception->getMessage());
        }

        $run->clearLease();
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

    public function getArtifact(string $artifactId, Context $context): ?ImportExportV2ArtifactEntity
    {
        $entity = $this->artifactRepository->search(new Criteria([$artifactId]), $context)->first();

        return $entity instanceof ImportExportV2ArtifactEntity ? $entity : null;
    }

    private function getProfile(string $name, Context $context): ImportExportV2ProfileEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $name));

        $entity = $this->profileRepository->search($criteria, $context)->first();
        if (!$entity instanceof ImportExportV2ProfileEntity) {
            throw ImportExportV2Exception::profileNotFound($name);
        }

        return $entity;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createRun(string $type, string $profileName, array $options, Context $context): ImportExportV2RunEntity
    {
        $run = new ImportExportV2RunEntity();
        $run->setId(Uuid::randomHex());
        $run->setType($type);
        $run->setProfileName($profileName);
        $run->markQueued();
        $run->setFailures([]);
        $run->setCursor([
            'offset' => 0,
            'chunkSize' => $this->resolveChunkSize($options),
        ]);
        $run->setRecordIds([]);

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
            'failures' => $run->getFailures(),
            'cursor' => $run->getCursor(),
            'totalRecords' => $run->getTotalRecords(),
            'lastError' => $run->getLastError(),
            'processingToken' => $run->getProcessingToken(),
            'processingExpiresAt' => $run->getProcessingExpiresAt(),
            'inputArtifactId' => $run->getInputArtifactId(),
            'outputArtifactId' => $run->getOutputArtifactId(),
            'recordIds' => $run->getRecordIds(),
        ]], $context);
    }

    private function createArtifact(string $name, string $mimeType, string $contents, Context $context): ImportExportV2ArtifactEntity
    {
        $artifact = new ImportExportV2ArtifactEntity();
        $artifact->setId(Uuid::randomHex());
        $artifact->setName($name);
        $artifact->setMimeType($mimeType);
        $artifact->setContents($contents);

        $this->saveArtifact($artifact, $context);

        return $artifact;
    }

    private function saveArtifact(ImportExportV2ArtifactEntity $artifact, Context $context): void
    {
        $this->artifactRepository->upsert([[
            'id' => $artifact->getId(),
            'name' => $artifact->getName(),
            'mimeType' => $artifact->getMimeType(),
            'contents' => $artifact->getContents(),
        ]], $context);
    }

    /**
     * The lease prevents two workers from advancing the same run at the same time.
     * If a worker crashes, the next message can claim the run again after the lease expires.
     */
    private function claimLease(string $runId, string $processingToken): bool
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+' . self::LEASE_MINUTES . ' minutes');

        return $this->connection->executeStatement(
            'UPDATE `import_export_v2_run`
                SET `processing_token` = :processingToken,
                    `processing_expires_at` = :processingExpiresAt,
                    `updated_at` = :updatedAt
              WHERE `id` = :id
                AND `state` IN (:claimableStates)
                AND (`processing_token` IS NULL OR `processing_expires_at` IS NULL OR `processing_expires_at` < :now)',
            [
                'processingToken' => $processingToken,
                'processingExpiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updatedAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => Uuid::fromHexToBytes($runId),
                'claimableStates' => [
                    ImportExportV2RunEntity::STATE_QUEUED,
                    ImportExportV2RunEntity::STATE_RUNNING,
                    ImportExportV2RunEntity::STATE_CANCEL_REQUESTED,
                ],
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'claimableStates' => ArrayParameterType::STRING,
            ]
        ) > 0;
    }

    private function isCancelRequested(string $runId): bool
    {
        $state = $this->connection->fetchOne(
            'SELECT `state` FROM `import_export_v2_run` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($runId)]
        );

        return $state === ImportExportV2RunEntity::STATE_CANCEL_REQUESTED;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveChunkSize(array $options): int
    {
        $chunkSize = $options['chunkSize'] ?? self::DEFAULT_CHUNK_SIZE;

        return \is_numeric($chunkSize) && (int) $chunkSize > 0 ? (int) $chunkSize : self::DEFAULT_CHUNK_SIZE;
    }
}
