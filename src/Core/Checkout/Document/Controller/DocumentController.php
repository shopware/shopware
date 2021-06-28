<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Controller;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class DocumentController extends AbstractController
{
    /**
     * @var DocumentService
     */
    protected $documentService;

    /**
     * @var EntityRepositoryInterface
     */
    private $documentRepository;

    public function __construct(DocumentService $documentService, EntityRepositoryInterface $documentRepository)
    {
        $this->documentService = $documentService;
        $this->documentRepository = $documentRepository;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Get(
     *     path="/_action/document/{documentId}/{deepLinkCode}",
     *     summary="Download a document",
     *     description="Download a document by its identifier and deep link code.",
     *     operationId="downloadDocument",
     *     tags={"Admin API", "Document Management"},
     *     @OA\Parameter(
     *         name="documentId",
     *         description="Identifier of the document to be downloaded.",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="deepLinkCode",
     *         description="A unique hash code which was generated when the document was created.",
     *         @OA\Schema(type="string"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="download",
     *         description="This parameter controls the `Content-Disposition` header. If set to `true` the header will be set to `attachment` else `inline`.",
     *         @OA\Schema(type="boolean", default=false),
     *         in="query",
     *     ),
     *      @OA\Response(
     *          response="200",
     *          description="The document.",
     *          @OA\MediaType(
     *              mediaType="application/octet-stream",
     *              @OA\Schema(
     *                  type="string",
     *                  format="binary"
     *              )
     *          )
     *     )
     * )
     * @Route("/api/_action/document/{documentId}/{deepLinkCode}", name="api.action.download.document", methods={"GET"})
     */
    public function downloadDocument(Request $request, string $documentId, string $deepLinkCode, Context $context): Response
    {
        $download = $request->query->getBoolean('download', false);

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('id', $documentId),
            new EqualsFilter('deepLinkCode', $deepLinkCode),
        ]));
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        $document = $this->documentRepository->search($criteria, $context)->get($documentId);

        if (!$document) {
            throw new InvalidDocumentException($documentId);
        }

        $generatedDocument = $this->documentService->getDocument($document, $context);

        return $this->createResponse(
            $generatedDocument->getFilename(),
            $generatedDocument->getFileBlob(),
            $download,
            $generatedDocument->getContentType()
        );
    }

    /**
     * @Since("6.0.0.0")
     * @Route(
     *     "/api/_action/order/{orderId}/{deepLinkCode}/document/{documentTypeName}/preview",
     *     name="api.action.document.preview",
     *     methods={"GET"}
     * )
     */
    public function previewDocument(
        Request $request,
        string $orderId,
        string $deepLinkCode,
        string $documentTypeName,
        Context $context
    ): Response {
        $config = $request->query->get('config');
        $config = \is_string($config) ? json_decode($config, true, 512, \JSON_THROW_ON_ERROR) : [];
        $documentConfig = DocumentConfigurationFactory::createConfiguration($config);

        $fileType = $request->query->getAlnum('fileType', FileTypes::PDF);
        $download = $request->query->getBoolean('download', false);

        $generatedDocument = $this->documentService->preview(
            $orderId,
            $deepLinkCode,
            $documentTypeName,
            $fileType,
            $documentConfig,
            $context
        );

        return $this->createResponse(
            $generatedDocument->getFilename(),
            $generatedDocument->getFileBlob(),
            $download,
            $generatedDocument->getContentType()
        );
    }

    private function createResponse(string $filename, string $content, bool $forceDownload, string $contentType): Response
    {
        $response = new Response($content);

        $disposition = HeaderUtils::makeDisposition(
            $forceDownload ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $filename,
            // only printable ascii
            preg_replace('/[\x00-\x1F\x7F-\xFF]/', '_', $filename)
        );

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
