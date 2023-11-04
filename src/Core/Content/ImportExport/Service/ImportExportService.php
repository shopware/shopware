<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Exception\ProfileNotFoundException;
use Shopware\Core\Content\ImportExport\Exception\ProfileWrongTypeException;
use Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal We might break this in v6.2
 *
 * @phpstan-type Config array{mapping?: ?array<array<string, mixed>>, updateBy?: ?array<string, mixed>, parameters?: ?array<string, mixed>}
 */
#[Package('system-settings')]
class ImportExportService
{
    public function __construct(
        private readonly EntityRepository $logRepository,
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $profileRepository,
        private readonly AbstractFileService $fileService
    ) {
    }

    /**
     * @param Config $config
     */
    public function prepareExport(
        Context $context,
        string $profileId,
        \DateTimeInterface $expireDate,
        ?string $originalFileName = null,
        array $config = [],
        ?string $destinationPath = null,
        string $activity = ImportExportLogEntity::ACTIVITY_EXPORT
    ): ImportExportLogEntity {
        $profileEntity = $this->findProfile($context, $profileId);

        if (!\in_array($profileEntity->getType(), [ImportExportProfileEntity::TYPE_EXPORT, ImportExportProfileEntity::TYPE_IMPORT_EXPORT], true)) {
            throw new ProfileWrongTypeException($profileEntity->getId(), $profileEntity->getType());
        }

        if ($originalFileName === null) {
            $originalFileName = $this->fileService->generateFilename($profileEntity);
        }

        if ($profileEntity->getMapping() !== null) {
            $mappings = MappingCollection::fromIterable($profileEntity->getMapping());
            $profileEntity->setMapping($mappings->sortByPosition());
        }

        $fileEntity = $this->fileService->storeFile($context, $expireDate, null, $originalFileName, $activity, $destinationPath);

        return $this->createLog($context, $activity, $fileEntity, $profileEntity, $config);
    }

    /**
     * @param Config $config
     */
    public function prepareImport(
        Context $context,
        string $profileId,
        \DateTimeInterface $expireDate,
        UploadedFile $file,
        array $config = [],
        bool $dryRun = false
    ): ImportExportLogEntity {
        $profileEntity = $this->findProfile($context, $profileId);

        if (!\in_array($profileEntity->getType(), [ImportExportProfileEntity::TYPE_IMPORT, ImportExportProfileEntity::TYPE_IMPORT_EXPORT], true)) {
            throw new ProfileWrongTypeException($profileEntity->getId(), $profileEntity->getType());
        }

        $type = $this->fileService->detectType($file);
        if ($type !== $profileEntity->getFileType()) {
            throw new UnexpectedFileTypeException($file->getClientMimeType(), $profileEntity->getFileType());
        }

        $fileEntity = $this->fileService->storeFile($context, $expireDate, $file->getPathname(), $file->getClientOriginalName(), ImportExportLogEntity::ACTIVITY_IMPORT);
        $activity = $dryRun ? ImportExportLogEntity::ACTIVITY_DRYRUN : ImportExportLogEntity::ACTIVITY_IMPORT;

        return $this->createLog($context, $activity, $fileEntity, $profileEntity, $config);
    }

    public function cancel(Context $context, string $logId): void
    {
        $logEntity = $this->findLog($context, $logId);

        if ($logEntity === null) {
            throw new ProcessingException('LogEntity not found');
        }

        $canceledProgress = new Progress($logId, Progress::STATE_ABORTED);
        $canceledProgress->addProcessedRecords($logEntity->getRecords());

        $this->saveProgress($canceledProgress);
    }

    public function getProgress(string $logId, int $offset): Progress
    {
        $criteria = new Criteria([$logId]);
        $criteria->addAssociation('file');
        $current = $this->logRepository->search($criteria, Context::createDefaultContext())->first();
        if (!$current instanceof ImportExportLogEntity) {
            throw new \RuntimeException('ImportExportLog "' . $logId . '" not found');
        }

        $progress = new Progress(
            $current->getId(),
            $current->getState(),
            $offset
        );
        if ($current->getInvalidRecordsLogId()) {
            $progress->setInvalidRecordsLogId($current->getInvalidRecordsLogId());
        }

        $progress->addProcessedRecords($current->getRecords());

        return $progress;
    }

    /**
     * @param array<array<mixed>>|null $result
     */
    public function saveProgress(Progress $progress, ?array $result = null): void
    {
        $logData = [
            'id' => $progress->getLogId(),
            'records' => $progress->getProcessedRecords(),
        ];
        if ($progress->getState() !== Progress::STATE_PROGRESS) {
            $logData['state'] = $progress->getState();
        }
        if ($progress->getInvalidRecordsLogId()) {
            $logData['invalidRecordsLogId'] = $progress->getInvalidRecordsLogId();
        }
        if ($result) {
            $logData['result'] = $result;
        }

        $context = Context::createDefaultContext();
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($logData): void {
            $this->logRepository->update([$logData], $context);
        });
    }

    private function findLog(Context $context, string $logId): ?ImportExportLogEntity
    {
        $criteria = new Criteria([$logId]);
        $criteria->addAssociation('profile');
        $criteria->addAssociation('file');
        $criteria->addAssociation('invalidRecordsLog.file');
        /** @var ImportExportLogCollection $result */
        $result = $this->logRepository->search($criteria, $context)->getEntities();

        return $result->get($logId);
    }

    private function findProfile(Context $context, string $profileId): ImportExportProfileEntity
    {
        $profile = $this->profileRepository
            ->search(new Criteria([$profileId]), $context)
            ->first();

        if ($profile instanceof ImportExportProfileEntity) {
            return $profile;
        }

        throw new ProfileNotFoundException($profileId);
    }

    /**
     * @param Config $config
     */
    private function createLog(
        Context $context,
        string $activity,
        ImportExportFileEntity $file,
        ImportExportProfileEntity $profile,
        array $config
    ): ImportExportLogEntity {
        $logEntity = new ImportExportLogEntity();
        $logEntity->setId(Uuid::randomHex());
        $logEntity->setActivity($activity);
        $logEntity->setState(Progress::STATE_PROGRESS);
        $logEntity->setProfileId($profile->getId());
        $logEntity->setProfileName($profile->getTranslation('label'));
        $logEntity->setFileId($file->getId());
        $logEntity->setRecords(0);
        $logEntity->setConfig($this->getConfig($profile, $config));

        $contextSource = $context->getSource();
        $userId = $contextSource instanceof AdminApiSource ? $contextSource->getUserId() : null;
        if ($userId !== null) {
            $logEntity->setUsername($this->findUser($context, $userId)->getUsername());
            $logEntity->setUserId($userId);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($logEntity): void {
            $logData = array_filter($logEntity->jsonSerialize(), fn ($value) => $value !== null);
            $this->logRepository->create([$logData], $context);
        });

        $logEntity->setProfile($profile);
        $logEntity->setFile($file);

        return $logEntity;
    }

    private function findUser(Context $context, string $userId): UserEntity
    {
        return $this->userRepository->search(new Criteria([$userId]), $context)->first();
    }

    /**
     * @param Config $config
     *
     * @return Config
     */
    private function getConfig(ImportExportProfileEntity $profileEntity, array $config): array
    {
        $parameters = $profileEntity->getConfig();

        $parameters['delimiter'] = $profileEntity->getDelimiter();
        $parameters['enclosure'] = $profileEntity->getEnclosure();
        $parameters['sourceEntity'] = $profileEntity->getSourceEntity();
        $parameters['fileType'] = $profileEntity->getFileType();
        $parameters['profileName'] = $profileEntity->getName();

        return [
            'mapping' => $config['mapping'] ?? $profileEntity->getMapping(),
            'updateBy' => $config['updateBy'] ?? $profileEntity->getUpdateBy(),
            'parameters' => array_merge($parameters, $config['parameters'] ?? []),
        ];
    }
}
