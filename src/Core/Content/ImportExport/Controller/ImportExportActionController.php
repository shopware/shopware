<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Controller;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\ImportExport\Exception\ProfileNotFoundException;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Service\AbstractMappingService;
use Shopware\Core\Content\ImportExport\Service\DownloadService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Service\SupportedFeaturesService;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class ImportExportActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SupportedFeaturesService $supportedFeaturesService,
        private readonly ImportExportService $importExportService,
        private readonly DownloadService $downloadService,
        private readonly EntityRepository $profileRepository,
        private readonly DataValidator $dataValidator,
        private readonly ImportExportLogDefinition $logDefinition,
        private readonly ApiVersionConverter $apiVersionConverter,
        private readonly ImportExportFactory $importExportFactory,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly MessageBusInterface $messageBus,
        private readonly AbstractMappingService $mappingService
    ) {
    }

    #[Route(path: '/api/_action/import-export/features', name: 'api.action.import_export.features', methods: ['GET'])]
    public function features(): JsonResponse
    {
        return new JsonResponse([
            'entities' => $this->supportedFeaturesService->getEntities(),
            'fileTypes' => $this->supportedFeaturesService->getFileTypes(),
            'uploadFileSizeLimit' => $this->supportedFeaturesService->getUploadFileSizeLimit(),
        ]);
    }

    #[Route(path: '/api/_action/import-export/prepare', name: 'api.action.import_export.initiate', methods: ['POST'])]
    public function initiate(Request $request, Context $context): JsonResponse
    {
        $profileId = (string) $request->request->get('profileId');
        $expireDate = (string) $request->request->get('expireDate');

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $profile = $this->findProfile($context, $profileId);
        $expireDate = new \DateTimeImmutable($expireDate);

        if ($file !== null) {
            $log = $this->importExportService->prepareImport(
                $context,
                $profile->getId(),
                $expireDate,
                $file,
                $request->request->all('config'),
                $request->request->has('dryRun')
            );

            unlink($file->getPathname());
        } else {
            $this->checkAllowedReadPrivileges($profile, $context);

            $log = $this->importExportService->prepareExport(
                $context,
                $profile->getId(),
                $expireDate,
                null,
                $request->request->all('config')
            );
        }

        return new JsonResponse(['log' => $this->apiVersionConverter->convertEntity($this->logDefinition, $log)]);
    }

    #[Route(path: '/api/_action/import-export/process', name: 'api.action.import_export.process', methods: ['POST'])]
    public function process(Request $request, Context $context): Response
    {
        $logId = strtolower((string) $request->request->get('logId'));

        $importExport = $this->importExportFactory->create($logId, 50, 50);
        $logEntity = $importExport->getLogEntity();

        $message = new ImportExportMessage($context, $logEntity->getId(), $logEntity->getActivity());

        $this->messageBus->dispatch($message);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/import-export/file/prepare-download/{fileId}', name: 'api.action.import_export.file.prepare-download', methods: ['POST'])]
    public function prepareDownload(string $fileId, Context $context): Response
    {
        $token = $this->downloadService->regenerateToken($context, $fileId);

        return new JsonResponse(['accessToken' => $token]);
    }

    #[Route(path: '/api/_action/import-export/file/download', name: 'api.action.import_export.file.download', defaults: ['auth_required' => false], methods: ['GET'])]
    public function download(Request $request, Context $context): Response
    {
        /** @var array<string> $params */
        $params = $request->query->all();
        $definition = new DataValidationDefinition();
        $definition->add('fileId', new NotBlank(), new Type('string'));
        $definition->add('accessToken', new NotBlank(), new Type('string'));
        $this->dataValidator->validate($params, $definition);

        return $this->downloadService->createFileResponse($context, $params['fileId'], $params['accessToken']);
    }

    #[Route(path: '/api/_action/import-export/cancel', name: 'api.action.import_export.cancel', methods: ['POST'])]
    public function cancel(Request $request, Context $context): Response
    {
        $logId = $request->request->get('logId');

        if (!\is_string($logId)) {
            throw RoutingException::invalidRequestParameter('logId');
        }

        $this->importExportService->cancel($context, $logId);
        $this->importExportFactory->create($logId)->abort();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/import-export/prepare-template-file-download', name: 'api.action.import_export.template_file.prepare_download', methods: ['POST'])]
    public function prepareTemplateFileDownload(Request $request, Context $context): Response
    {
        $profileId = $request->query->get('profileId');
        if (!\is_string($profileId)) {
            throw RoutingException::invalidRequestParameter('profileId');
        }
        $profileId = strtolower($profileId);

        $fileId = $this->mappingService->createTemplate($context, $profileId);
        $token = $this->downloadService->regenerateToken($context, $fileId);

        return new JsonResponse([
            'fileId' => $fileId,
            'accessToken' => $token,
        ]);
    }

    #[Route(path: '/api/_action/import-export/mapping-from-template', name: 'api.action.import_export.template_file.mapping', methods: ['POST'])]
    public function mappingFromTemplate(Request $request, Context $context): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $sourceEntity = $request->request->get('sourceEntity');
        $delimiter = (string) $request->request->get('delimiter', ';');
        $enclosure = (string) $request->request->get('enclosure', '"');

        if ($file === null || !$file->isValid()) {
            throw RoutingException::invalidRequestParameter('file');
        }

        if (!\is_string($sourceEntity)) {
            throw RoutingException::invalidRequestParameter('sourceEntity');
        }

        $mapping = $this->mappingService->getMappingFromTemplate($context, $file, $sourceEntity, $delimiter, $enclosure);

        return new JsonResponse($mapping);
    }

    /**
     * @throws ProfileNotFoundException
     */
    private function findProfile(Context $context, string $profileId): ImportExportProfileEntity
    {
        $profile = $this->profileRepository
            ->search(new Criteria([$profileId]), $context)
            ->getEntities()
            ->get($profileId);

        if ($profile instanceof ImportExportProfileEntity) {
            return $profile;
        }

        throw new ProfileNotFoundException($profileId);
    }

    private function checkAllowedReadPrivileges(ImportExportProfileEntity $profile, Context $context): void
    {
        $missingPrivileges = [];

        $sourceEntity = $profile->getSourceEntity();
        $privilege = sprintf('%s:%s', $sourceEntity, AclRoleDefinition::PRIVILEGE_READ);

        if (!$context->isAllowed($privilege)) {
            $missingPrivileges[] = $privilege;
        }

        $definition = $this->definitionInstanceRegistry->getByEntityName($sourceEntity);
        $mappings = $profile->getMapping() ?? [];

        $mappedKeys = array_column($mappings, 'key');
        $propertyPaths = array_map(fn (string $key): array => explode('.', $key), $mappedKeys);

        foreach ($propertyPaths as $properties) {
            $missingPrivileges = $this->getMissingPrivilges($properties, $definition, $context, $missingPrivileges);
        }

        if (!empty($missingPrivileges)) {
            throw new MissingPrivilegeException($missingPrivileges);
        }
    }

    /**
     * @param array<string> $properties
     * @param array<string> $missingPrivileges
     *
     * @return array<string>
     */
    private function getMissingPrivilges(
        array $properties,
        EntityDefinition $definition,
        Context $context,
        array $missingPrivileges
    ): array {
        $property = (string) array_shift($properties);

        $property = $definition->getField($property);

        if (!$property instanceof AssociationField || $property instanceof TranslationsAssociationField) {
            return $missingPrivileges;
        }

        $definition = $property->getReferenceDefinition();
        $privilege = sprintf('%s:%s', $definition->getEntityName(), AclRoleDefinition::PRIVILEGE_READ);

        if (!$context->isAllowed($privilege)) {
            $missingPrivileges[] = $privilege;
        }

        if (!empty($properties)) {
            $missingPrivileges = $this->getMissingPrivilges($properties, $definition, $context, $missingPrivileges);
        }

        return $missingPrivileges;
    }
}
