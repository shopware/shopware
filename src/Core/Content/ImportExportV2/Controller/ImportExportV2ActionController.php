<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Controller;

use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\File\FileService;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileCollection;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Service\RunService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @param EntityRepository<ImportExportV2ProfileCollection> $profileRepository
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('fundamentals@after-sales')]
class ImportExportV2ActionController
{
    public function __construct(
        private readonly RunService $runService,
        private readonly FileService $fileService,
        private readonly EntityRepository $profileRepository
    ) {
    }

    // TODO: add create and update endpoints for profiles

    #[Route(path: '/api/_action/import-export-v2/profiles', name: 'api.action.import_export_v2.profiles', methods: ['GET'])]
    public function listProfiles(Context $context): JsonResponse
    {
        $profiles = $this->profileRepository->search(new Criteria(), $context)->getEntities();

        return new JsonResponse([
            'profiles' => array_values(array_map(
                static fn (ImportExportV2ProfileEntity $profile): array => $profile->jsonSerialize(),
                $profiles->getElements()
            )),
        ]);
    }

    #[Route(path: '/api/_action/import-export-v2/import', name: 'api.action.import_export_v2.import', methods: ['POST'])]
    public function startImport(Request $request, Context $context): JsonResponse
    {
        $profile = $this->getProfile((string) $request->request->get('profileName'), $context);

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw ImportExportV2Exception::invalidRequestParameter('file');
        }

        $inputPath = $file->getRealPath();
        if ($inputPath === false) {
            throw ImportExportV2Exception::invalidRequestParameter('file');
        }

        $run = $this->runService->startImport($profile, $inputPath, $context, $file->getClientOriginalName());

        return new JsonResponse(['run' => $run->jsonSerialize()], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/import-export-v2/export', name: 'api.action.import_export_v2.export', methods: ['POST'])]
    public function startExport(Request $request, Context $context): JsonResponse
    {
        $profile = $this->getProfile($request->getPayload()->getString('profileName'), $context);

        $run = $this->runService->startExport($profile, $context);

        return new JsonResponse(['run' => $run->jsonSerialize()], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/import-export-v2/run/{runId}/cancel', name: 'api.action.import_export_v2.run.cancel', methods: ['POST'])]
    public function cancelRun(string $runId, Context $context): JsonResponse
    {
        $run = $this->runService->cancel($runId, $context);

        return new JsonResponse(['run' => $run->jsonSerialize()]);
    }

    #[Route(path: '/api/_action/import-export-v2/run/{runId}/resume', name: 'api.action.import_export_v2.run.resume', methods: ['POST'])]
    public function resumeRun(string $runId, Context $context): JsonResponse
    {
        $run = $this->runService->resume($runId, $context);

        return new JsonResponse(['run' => $run->jsonSerialize()]);
    }

    #[Route(path: '/api/_action/import-export-v2/run/{runId}', name: 'api.action.import_export_v2.run', methods: ['GET'])]
    public function getRun(string $runId, Context $context): JsonResponse
    {
        $run = $this->runService->getRun($runId, $context);
        if ($run === null) {
            throw ImportExportV2Exception::runNotFound($runId);
        }

        return new JsonResponse(['run' => $run->jsonSerialize()]);
    }

    #[Route(path: '/api/_action/import-export-v2/file/{fileId}', name: 'api.action.import_export_v2.file', methods: ['GET'])]
    public function getFile(string $fileId, Context $context): JsonResponse
    {
        $file = $this->fileService->getFile($fileId, $context);
        if ($file === null) {
            throw ImportExportV2Exception::fileNotFound($fileId);
        }

        return new JsonResponse([
            'file' => [
                ...$file->jsonSerialize(),
                'contents' => $this->fileService->readFileContents($file),
            ],
        ]);
    }

    #[Route(path: '/api/_action/import-export-v2/file/{fileId}/download', name: 'api.action.import_export_v2.file.download', methods: ['GET'])]
    public function downloadFile(string $fileId, Context $context): Response
    {
        $file = $this->fileService->getFile($fileId, $context);
        if ($file === null) {
            throw ImportExportV2Exception::fileNotFound($fileId);
        }

        return new Response(
            $this->fileService->readFileContents($file),
            Response::HTTP_OK,
            [
                'Content-Type' => $file->getMimeType() ?? 'application/octet-stream',
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $file->getName() ?? $file->getId()),
            ]
        );
    }

    private function getProfile(string $profileName, Context $context): ImportExportV2ProfileEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('technicalName', $profileName));

        $profile = $this->profileRepository->search($criteria, $context)->first();
        if (!$profile instanceof ImportExportV2ProfileEntity) {
            throw ImportExportV2Exception::profileNotFound($profileName);
        }

        return $profile;
    }
}
