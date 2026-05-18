<?php declare(strict_types=1);

namespace Scripts\Examples;

use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Service\RunService;
use Shopware\Core\Content\ImportExportV2\Support\FileService;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

require_once __DIR__ . '/base-script.php';
require_once __DIR__ . '/import-export-v2-product-profiles.php';

$env = 'prod'; // by default, kernel gets booted in dev

$kernel = require __DIR__ . '/../boot/boot.php';

class ImportExportV2ProductExport extends BaseScript
{
    public function run(): void
    {
        $context = Context::createCLIContext();

        $profiles = buildProductImportExportV2Profiles();

        $jsonProfile = $profiles['json'];
        $csvProfile = $profiles['csv'];

        $this->persistProfile($jsonProfile, $context);
        $this->persistProfile($csvProfile, $context);

        $jsonExport = $this->runExport($jsonProfile, $context);
        $csvExport = $this->runExport($csvProfile, $context);

        $convertedRecords = $this->decodeJsonExportRecords($jsonExport['contents']);
        $this->writeLocalExampleOutput('import-export-v2-product-export-output.json', $jsonExport['contents']);
        $this->writeLocalExampleOutput('import-export-v2-product-export-output.csv', $csvExport['contents']);

        echo "Converted export records:\n";
        echo json_encode($convertedRecords, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR) . "\n\n";

        echo "JSON output:\n";
        echo $jsonExport['contents'] . "\n\n";

        echo "CSV output:\n";
        echo $csvExport['contents'] . "\n";
    }

    private function persistProfile(ImportExportV2ProfileEntity $profile, Context $context): void
    {
        $profileRepository = $this->getProfileRepository();
        $existingId = $this->findExistingProfileId($profile->getTechnicalName(), $context);

        $profileData = [
            'id' => $existingId ?? Uuid::randomHex(),
            'technicalName' => $profile->getTechnicalName(),
            'entity' => $profile->getEntity(),
            'format' => $profile->getFormat(),
            'filters' => $profile->getFilters(),
            'recordPaths' => $profile->getRecordPaths(),
            'matchBy' => $profile->getMatchBy(),
            'fieldMappings' => $profile->getFieldMappings(),
        ];

        $profileRepository->upsert([$profileData], $context);
        $profile->setId($profileData['id']);
    }

    /**
     * @return array{contents: string}
     */
    private function runExport(ImportExportV2ProfileEntity $profile, Context $context): array
    {
        $runService = $this->getContainer()->get(RunService::class);
        \assert($runService instanceof RunService);

        $run = $runService->startExport($profile, $context);

        while (true) {
            $runService->process($run->getId(), $context);

            $reloadedRun = $runService->getRun($run->getId(), $context);
            if (!$reloadedRun instanceof ImportExportV2RunEntity) {
                throw new \RuntimeException('Export run could not be reloaded.');
            }

            $run = $reloadedRun;

            if (\in_array($run->getState(), [
                ImportExportV2RunEntity::STATE_COMPLETED,
                ImportExportV2RunEntity::STATE_FAILED,
                ImportExportV2RunEntity::STATE_CANCELED,
            ], true)) {
                break;
            }
        }

        $fileService = $this->getContainer()->get(FileService::class);
        \assert($fileService instanceof FileService);

        $file = $fileService->getFile($run->getFileId(), $context);
        if (!$file instanceof ImportExportV2FileEntity) {
            throw new \RuntimeException('Export file could not be loaded.');
        }

        $path = $file->getPath();
        if (!\is_string($path) || $path === '') {
            throw new \RuntimeException('Export file path is missing.');
        }

        $filesystem = $this->getContainer()->get('shopware.filesystem.private');

        return [
            'contents' => $filesystem->read($path),
        ];
    }

    /**
     * The JSON export already contains the exact `ImportExportRecord` shape we
     * want to show in this example, so we use it as the preview source instead
     * of rebuilding the records in a second code path.
     *
     * @return list<array<string, mixed>>
     */
    private function decodeJsonExportRecords(string $contents): array
    {
        if (trim($contents) === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    private function writeLocalExampleOutput(string $filename, string $contents): string
    {
        $path = __DIR__ . '/' . $filename;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @return EntityRepository<object>
     */
    private function getProfileRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('import_export_v2_profile.repository');
        \assert($repository instanceof EntityRepository);

        return $repository;
    }

    private function findExistingProfileId(string $technicalName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        $entity = $this->getProfileRepository()->search($criteria, $context)->first();

        return $entity instanceof ImportExportV2ProfileEntity ? $entity->getId() : null;
    }
}

(new ImportExportV2ProductExport($kernel))->run();
