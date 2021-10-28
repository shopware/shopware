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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
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

/**
 * @RouteScope(scopes={"api"})
 */
class ImportExportActionController extends AbstractController
{
    private SupportedFeaturesService $supportedFeaturesService;

    private ImportExportService $importExportService;

    private DownloadService $downloadService;

    private EntityRepositoryInterface $profileRepository;

    private DataValidator $dataValidator;

    private ImportExportLogDefinition $logDefinition;

    private ApiVersionConverter $apiVersionConverter;

    private ImportExportFactory $importExportFactory;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private MessageBusInterface $messageBus;

    private AbstractMappingService $mappingService;

    public function __construct(
        SupportedFeaturesService $supportedFeaturesService,
        ImportExportService $initiationService,
        DownloadService $downloadService,
        EntityRepositoryInterface $profileRepository,
        DataValidator $dataValidator,
        ImportExportLogDefinition $logDefinition,
        ApiVersionConverter $apiVersionConverter,
        ImportExportFactory $importExportFactory,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        MessageBusInterface $messageBus,
        AbstractMappingService $mappingService
    ) {
        $this->supportedFeaturesService = $supportedFeaturesService;
        $this->importExportService = $initiationService;
        $this->downloadService = $downloadService;
        $this->profileRepository = $profileRepository;
        $this->dataValidator = $dataValidator;
        $this->logDefinition = $logDefinition;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->importExportFactory = $importExportFactory;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->messageBus = $messageBus;
        $this->mappingService = $mappingService;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/features", name="api.action.import_export.features", methods={"GET"})
     */
    public function features(): JsonResponse
    {
        return new JsonResponse([
            'entities' => $this->supportedFeaturesService->getEntities(),
            'fileTypes' => $this->supportedFeaturesService->getFileTypes(),
            'uploadFileSizeLimit' => $this->supportedFeaturesService->getUploadFileSizeLimit(),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/prepare", name="api.action.import_export.initiate", methods={"POST"})
     */
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
                Feature::isActive('FEATURE_NEXT_8097') && $request->request->has('dryRun')
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

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/process", name="api.action.import_export.process", methods={"POST"})
     */
    public function process(Request $request, Context $context): Response
    {
        $logId = strtolower((string) $request->request->get('logId'));

        $importExport = $this->importExportFactory->create($logId, 50, 50);
        $logEntity = $importExport->getLogEntity();

        $this->messageBus->dispatch(new ImportExportMessage($context, $logEntity->getId(), $logEntity->getActivity()));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.3.1")
     * @Route("/api/_action/import-export/file/prepare-download/{fileId}", name="api.action.import_export.file.prepare-download", methods={"POST"})
     */
    public function prepareDownload(string $fileId, Context $context): Response
    {
        $token = $this->downloadService->regenerateToken($context, $fileId);

        return new JsonResponse(['accessToken' => $token]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/file/download", name="api.action.import_export.file.download", defaults={"auth_required"=false}, methods={"GET"})
     */
    public function download(Request $request, Context $context): Response
    {
        /** @var string[] $params */
        $params = $request->query->all();
        $definition = new DataValidationDefinition();
        $definition->add('fileId', new NotBlank(), new Type('string'));
        $definition->add('accessToken', new NotBlank(), new Type('string'));
        $this->dataValidator->validate($params, $definition);

        return $this->downloadService->createFileResponse($context, $params['fileId'], $params['accessToken']);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/cancel", name="api.action.import_export.cancel", methods={"POST"})
     */
    public function cancel(Request $request, Context $context): Response
    {
        $logId = $request->request->get('logId');

        if (!\is_string($logId)) {
            throw new InvalidRequestParameterException('logId');
        }

        $this->importExportService->cancel($context, $logId);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     * @Route("/api/_action/import-export/prepare-template-file-download", name="api.action.import_export.template_file.prepare_download", methods={"POST"})
     */
    public function prepareTemplateFileDownload(Request $request, Context $context): Response
    {
        $profileId = $request->query->get('profileId');
        if (!\is_string($profileId)) {
            throw new InvalidRequestParameterException('profileId');
        }
        $profileId = strtolower($profileId);

        $fileId = $this->mappingService->createTemplate($context, $profileId);
        $token = $this->downloadService->regenerateToken($context, $fileId);

        return new JsonResponse([
            'fileId' => $fileId,
            'accessToken' => $token,
        ]);
    }

    /**
     * @internal (flag:FEATURE_NEXT_15998)
     * @Route("/api/_action/import-export/mapping-from-template", name="api.action.import_export.template_file.mapping", methods={"POST"})
     */
    public function mappingFromTemplate(Request $request, Context $context): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $sourceEntity = $request->request->get('sourceEntity');
        $delimiter = (string) $request->request->get('delimiter', ';');
        $enclosure = (string) $request->request->get('enclosure', '"');

        if ($file === null || !$file->isValid()) {
            throw new InvalidRequestParameterException('file');
        }

        if (!\is_string($sourceEntity)) {
            throw new InvalidRequestParameterException('sourceEntity');
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
        $propertyPaths = array_map(function (string $key): array {
            return explode('.', $key);
        }, $mappedKeys);

        foreach ($propertyPaths as $properties) {
            $missingPrivileges = $this->getMissingPrivilges($properties, $definition, $context, $missingPrivileges);
        }

        if (!empty($missingPrivileges)) {
            throw new MissingPrivilegeException($missingPrivileges);
        }
    }

    private function getMissingPrivilges(
        array $properties,
        EntityDefinition $definition,
        Context $context,
        array $missingPrivileges
    ): array {
        $property = array_shift($properties);

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
