<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Controller;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Exception\ProfileNotFoundException;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\DownloadService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Service\SupportedFeaturesService;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @RouteScope(scopes={"api"})
 */
class ImportExportActionController extends AbstractController
{
    /**
     * @var SupportedFeaturesService
     */
    private $supportedFeaturesService;

    /**
     * @var ImportExportService
     */
    private $importExportService;

    /**
     * @var DownloadService
     */
    private $downloadService;

    /**
     * @var EntityRepositoryInterface
     */
    private $profileRepository;

    /**
     * @var DataValidator
     */
    private $dataValidator;

    /**
     * @var ImportExportLogDefinition
     */
    private $logDefinition;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var ImportExportFactory
     */
    private $importExportFactory;

    public function __construct(
        SupportedFeaturesService $supportedFeaturesService,
        ImportExportService $initiationService,
        DownloadService $downloadService,
        EntityRepositoryInterface $profileRepository,
        DataValidator $dataValidator,
        ImportExportLogDefinition $logDefinition,
        ApiVersionConverter $apiVersionConverter,
        ImportExportFactory $importExportFactory
    ) {
        $this->supportedFeaturesService = $supportedFeaturesService;
        $this->importExportService = $initiationService;
        $this->downloadService = $downloadService;
        $this->profileRepository = $profileRepository;
        $this->dataValidator = $dataValidator;
        $this->logDefinition = $logDefinition;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->importExportFactory = $importExportFactory;
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
        $params = $request->request->all();
        $definition = new DataValidationDefinition();
        $definition->add('profileId', new NotBlank(), new Type('string'));
        $definition->add('expireDate', new NotBlank(), new Type('string'));
        $this->dataValidator->validate($params, $definition);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $profile = $this->findProfile($context, $params['profileId']);
        $expireDate = new \DateTimeImmutable($params['expireDate']);

        if ($file !== null) {
            $log = $this->importExportService->prepareImport(
                $context,
                $profile->getId(),
                $expireDate,
                $file,
                $params['config'] ?? []
            );

            unlink($file->getPathname());
        } else {
            $log = $this->importExportService->prepareExport(
                $context,
                $profile->getId(),
                $expireDate,
                null,
                $params['config'] ?? []
            );
        }

        return new JsonResponse(['log' => $this->apiVersionConverter->convertEntity($this->logDefinition, $log)]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/process", name="api.action.import_export.process", methods={"POST"})
     */
    public function process(Request $request, Context $context): JsonResponse
    {
        $params = $request->request->all();
        $definition = new DataValidationDefinition();
        $definition->add('logId', new NotBlank(), new Type('string'));
        $definition->add('offset', new NotBlank(), new Type('int'));
        $this->dataValidator->validate($params, $definition);

        $logId = mb_strtolower($params['logId']);
        $offset = $params['offset'];

        $importExport = $this->importExportFactory->create($logId, 50, 50);
        $logEntity = $importExport->getLogEntity();

        if ($logEntity->getActivity() === 'import') {
            $progress = $importExport->import($context, $offset);
        } elseif ($logEntity->getActivity() === 'export') {
            $progress = $importExport->export($context, new Criteria(), $offset);
        } else {
            throw new ProcessingException('Unknown activity');
        }

        return new JsonResponse(['progress' => $progress->jsonSerialize()]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/file/download", name="api.action.import_export.file.download", defaults={"auth_required"=false}, methods={"GET"})
     */
    public function download(Request $request, Context $context): Response
    {
        $params = $request->query->all();
        $definition = new DataValidationDefinition();
        $definition->add('fileId', new NotBlank(), new Type('string'));
        $definition->add('accessToken', new NotBlank(), new Type('string'));
        $this->dataValidator->validate($params, $definition);

        $response = $this->downloadService->createFileResponse($context, $params['fileId'], $params['accessToken']);

        return $response;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/import-export/cancel", name="api.action.import_export.cancel", methods={"POST"})
     */
    public function cancel(Request $request, Context $context): Response
    {
        $params = $request->request->all();
        $definition = new DataValidationDefinition();
        $definition->add('logId', new NotBlank(), new Type('string'));
        $this->dataValidator->validate($params, $definition);

        $this->importExportService->cancel($context, $params['logId']);

        return new Response('', Response::HTTP_NO_CONTENT);
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
}
