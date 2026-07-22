<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Controller;

use Shopware\Core\Content\ImportExportV2\Job\Artifact\ImportExportV2ArtifactCollection;
use Shopware\Core\Content\ImportExportV2\Job\Artifact\ImportExportV2ArtifactEntity;
use Shopware\Core\Content\ImportExportV2\Job\Request\ExportRunRequest;
use Shopware\Core\Content\ImportExportV2\Job\Request\ImportRunRequest;
use Shopware\Core\Content\ImportExportV2\Job\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Job\Service\RunService;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileCollection;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @param EntityRepository<ImportExportV2ProfileCollection> $profileRepository
 * @param EntityRepository<ImportExportV2ArtifactCollection> $artifactRepository
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('fundamentals@after-sales')]
class ImportExportV2ActionController
{
    public function __construct(
        private readonly RunService $runService,
        private readonly EntityRepository $profileRepository,
        private readonly EntityRepository $artifactRepository
    ) {
    }

    #[Route(path: '/api/_action/import-export-v2/profiles', name: 'api.action.import_export_v2.profiles', methods: ['GET'])]
    public function listProfiles(Context $context): JsonResponse
    {
        $profiles = $this->profileRepository->search(new Criteria(), $context)->getEntities();

        return new JsonResponse([
            'profiles' => array_values(array_map(
                fn (ImportExportV2ProfileEntity $profile): array => $this->serializeProfile($profile),
                $profiles->getElements()
            )),
        ]);
    }

    #[Route(path: '/api/_action/import-export-v2/import', name: 'api.action.import_export_v2.import', methods: ['POST'])]
    public function startImport(Request $request, Context $context): JsonResponse
    {
        $payload = $request->getPayload();
        $profileName = $payload->getString('profileName');
        $inputContents = $payload->getString('inputContents');

        if ($profileName === '') {
            throw new BadRequestHttpException('The parameter "profileName" is required.');
        }

        if ($inputContents === '') {
            throw new BadRequestHttpException('The parameter "inputContents" is required.');
        }

        $run = $this->runService->startImport(new ImportRunRequest(
            $profileName,
            $inputContents,
            $this->getOptionalString($payload->get('inputFileName')),
            $this->getOptionalString($payload->get('inputMimeType')),
            $this->getRunOptions($payload->get('chunkSize'))
        ), $context);

        return new JsonResponse(['run' => $this->serializeRun($run)], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/import-export-v2/export', name: 'api.action.import_export_v2.export', methods: ['POST'])]
    public function startExport(Request $request, Context $context): JsonResponse
    {
        $payload = $request->getPayload();
        $profileName = $payload->getString('profileName');
        $recordIds = $payload->all('recordIds');

        if ($profileName === '') {
            throw new BadRequestHttpException('The parameter "profileName" is required.');
        }

        if (!\is_array($recordIds)) {
            throw new BadRequestHttpException('The parameter "recordIds" must be an array.');
        }

        $recordIds = array_values(array_filter($recordIds, static fn (mixed $id): bool => \is_string($id) && $id !== ''));
        if ($recordIds === []) {
            throw new BadRequestHttpException('The parameter "recordIds" must contain at least one id.');
        }

        $run = $this->runService->startExport(
            new ExportRunRequest($profileName, $recordIds, $this->getRunOptions($payload->get('chunkSize'))),
            $context
        );

        return new JsonResponse(['run' => $this->serializeRun($run)], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/import-export-v2/run/{runId}/cancel', name: 'api.action.import_export_v2.run.cancel', methods: ['POST'])]
    public function cancelRun(string $runId, Context $context): JsonResponse
    {
        $run = $this->runService->cancel($runId, $context);

        return new JsonResponse(['run' => $this->serializeRun($run)]);
    }

    #[Route(path: '/api/_action/import-export-v2/run/{runId}/resume', name: 'api.action.import_export_v2.run.resume', methods: ['POST'])]
    public function resumeRun(string $runId, Context $context): JsonResponse
    {
        $run = $this->runService->resume($runId, $context);

        return new JsonResponse(['run' => $this->serializeRun($run)]);
    }

    #[Route(path: '/api/_action/import-export-v2/run/{runId}', name: 'api.action.import_export_v2.run', methods: ['GET'])]
    public function getRun(string $runId, Context $context): JsonResponse
    {
        $run = $this->runService->getRun($runId, $context);
        if ($run === null) {
            throw new NotFoundHttpException(\sprintf('Import/export v2 run "%s" could not be found.', $runId));
        }

        return new JsonResponse(['run' => $this->serializeRun($run)]);
    }

    #[Route(path: '/api/_action/import-export-v2/artifact/{artifactId}', name: 'api.action.import_export_v2.artifact', methods: ['GET'])]
    public function getArtifact(string $artifactId, Context $context): JsonResponse
    {
        $artifact = $this->runService->getArtifact($artifactId, $context);
        if ($artifact === null) {
            throw new NotFoundHttpException(\sprintf('Import/export v2 artifact "%s" could not be found.', $artifactId));
        }

        return new JsonResponse(['artifact' => $this->serializeArtifact($artifact)]);
    }

    #[Route(path: '/api/_action/import-export-v2/artifact/{artifactId}/download', name: 'api.action.import_export_v2.artifact.download', methods: ['GET'])]
    public function downloadArtifact(string $artifactId, Context $context): Response
    {
        $artifact = $this->artifactRepository->search(new Criteria([$artifactId]), $context)->first();
        if (!$artifact instanceof ImportExportV2ArtifactEntity) {
            throw new NotFoundHttpException(\sprintf('Import/export v2 artifact "%s" could not be found.', $artifactId));
        }

        return new Response(
            $artifact->getContents(),
            Response::HTTP_OK,
            [
                'Content-Type' => $artifact->getMimeType(),
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $artifact->getName()),
            ]
        );
    }

    private function serializeRun(ImportExportV2RunEntity $run): array
    {
        return [
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
            'inputArtifactId' => $run->getInputArtifactId(),
            'outputArtifactId' => $run->getOutputArtifactId(),
            'recordIds' => $run->getRecordIds(),
        ];
    }

    private function serializeArtifact(ImportExportV2ArtifactEntity $artifact): array
    {
        return [
            'id' => $artifact->getId(),
            'name' => $artifact->getName(),
            'mimeType' => $artifact->getMimeType(),
            'contents' => $artifact->getContents(),
        ];
    }

    private function serializeProfile(ImportExportV2ProfileEntity $profile): array
    {
        return [
            'id' => $profile->getId(),
            'name' => $profile->getName(),
            'entity' => $profile->getEntity(),
            'format' => $profile->getFormat(),
            'identifierPaths' => $profile->getIdentifierPaths(),
            'payloadPaths' => $profile->getPayloadPaths(),
            'relationModes' => $profile->getRelationModes(),
            'fieldMappings' => $profile->getFieldMappings(),
        ];
    }

    private function getOptionalString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, int>
     */
    private function getRunOptions(mixed $chunkSize): array
    {
        if (\is_int($chunkSize) && $chunkSize > 0) {
            return ['chunkSize' => $chunkSize];
        }

        if (\is_string($chunkSize) && ctype_digit($chunkSize) && (int) $chunkSize > 0) {
            return ['chunkSize' => (int) $chunkSize];
        }

        return [];
    }
}
