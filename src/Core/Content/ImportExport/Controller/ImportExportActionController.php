<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Controller;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\ImportExport\Exception\ProfileNotFoundException;
use Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\DownloadService;
use Shopware\Core\Content\ImportExport\Service\InitiationService;
use Shopware\Core\Content\ImportExport\Service\ProcessingService;
use Shopware\Core\Content\ImportExport\Service\SupportedFeaturesService;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @var InitiationService
     */
    private $initiationService;

    /**
     * @var ProcessingService
     */
    private $processingService;

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
     * @var int
     */
    private $processingBatchSize;

    /**
     * @var ImportExportLogDefinition
     */
    private $logDefinition;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function __construct(
        SupportedFeaturesService $supportedFeaturesService,
        InitiationService $initiationService,
        ProcessingService $processingService,
        DownloadService $downloadService,
        EntityRepositoryInterface $profileRepository,
        DataValidator $dataValidator,
        ImportExportLogDefinition $logDefinition,
        ApiVersionConverter $apiVersionConverter,
        int $processingBatchSize
    ) {
        $this->supportedFeaturesService = $supportedFeaturesService;
        $this->initiationService = $initiationService;
        $this->processingService = $processingService;
        $this->downloadService = $downloadService;
        $this->profileRepository = $profileRepository;
        $this->dataValidator = $dataValidator;
        $this->processingBatchSize = $processingBatchSize;
        $this->logDefinition = $logDefinition;
        $this->apiVersionConverter = $apiVersionConverter;
    }

    /**
     * @Route("/api/v{version}/_action/import-export/features", name="api.action.import_export.features", methods={"GET"})
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
     * @Route("/api/v{version}/_action/import-export/initiate", name="api.action.import_export.initiate", methods={"POST"})
     */
    public function initiate(int $version, Request $request, Context $context): JsonResponse
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
            if ($file->getClientMimeType() !== $profile->getFileType()) {
                throw new UnexpectedFileTypeException($file->getClientMimeType(), $profile->getFileType());
            }

            $log = $this->initiationService->initiate(
                $context,
                'import',
                $profile,
                $expireDate,
                $file->getPathname(),
                $file->getClientOriginalName()
            );

            unlink($file->getPathname());
        } else {
            $log = $this->initiationService->initiate($context, 'export', $profile, $expireDate);
        }

        return new JsonResponse(['log' => $this->apiVersionConverter->convertEntity($this->logDefinition, $log, $version)]);
    }

    /**
     * @Route("/api/v{version}/_action/import-export/process", name="api.action.import_export.process", methods={"POST"})
     */
    public function process(Request $request, Context $context): JsonResponse
    {
        $params = $request->request->all();
        $definition = new DataValidationDefinition();
        $definition->add('logId', new NotBlank(), new Type('string'));
        $definition->add('offset', new NotBlank(), new Type('int'));
        $this->dataValidator->validate($params, $definition);

        $log = $this->processingService->findLog($context, $params['logId']);
        $recordIterator = $this->processingService->createRecordIterator($context, $log);
        $outer = new \LimitIterator($recordIterator, $params['offset'], $this->processingBatchSize);

        $processed = $this->processingService->process($context, $log, $outer);

        return new JsonResponse(['processed' => $processed]);
    }

    /**
     * @Route("/api/v{version}/_action/import-export/file/download", name="api.action.import_export.file.download", defaults={"auth_required"=false}, methods={"GET"})
     */
    public function download(Request $request, Context $context): Response
    {
        $params = $request->query->all();
        $definition = new DataValidationDefinition();
        $definition->add('fileId', new NotBlank(), new Type('string'));
        $definition->add('accessToken', new NotBlank(), new Type('string'));
        $this->dataValidator->validate($params, $definition);

        $response = $this->downloadService->createFileResponse($context, $params['fileId'], $params['accessToken']);

        $this->downloadService->regenerateToken($context, $params['fileId']);

        return $response;
    }

    /**
     * @Route("/api/v{version}/_action/import-export/cancel", name="api.action.import_export.cancel", methods={"POST"})
     */
    public function cancel(Request $request, Context $context): Response
    {
        $params = $request->request->all();
        $definition = new DataValidationDefinition();
        $definition->add('logId', new NotBlank(), new Type('string'));
        $this->dataValidator->validate($params, $definition);

        $this->processingService->cancel($context, $params['logId']);

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
