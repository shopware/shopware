<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Controller;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class DocumentController extends AbstractController
{
    /**
     * @deprecated tag:v6.5.0 - $documentService property will be removed
     */
    protected DocumentService $documentService;

    private DocumentGenerator $documentGenerator;

    private DocumentMerger $documentMerger;

    /**
     * @internal
     */
    public function __construct(
        DocumentService $documentService,
        DocumentGenerator $documentGenerator,
        DocumentMerger $documentMerger
    ) {
        $this->documentService = $documentService;
        $this->documentGenerator = $documentGenerator;
        $this->documentMerger = $documentMerger;
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
        /**
         * @deprecated tag:v6.5.0 - Put the check on the annotation instead: defaults={"_acl"={"document.viewer"}}
         */
        if (Feature::isActive('v6.5.0.0') && !$context->isAllowed('document.viewer')) {
            throw new MissingPrivilegeException(['document.viewer']);
        }

        $download = $request->query->getBoolean('download');

        $generatedDocument = $this->documentGenerator->readDocument($documentId, $context, $deepLinkCode);

        if ($generatedDocument === null) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        return $this->createResponse(
            $generatedDocument->getName(),
            $generatedDocument->getContent(),
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
        /**
         * @deprecated tag:v6.5.0 - Put the check on the annotation instead: defaults={"_acl"={"document.viewer"}}
         */
        if (Feature::isActive('v6.5.0.0') && !$context->isAllowed('document.viewer')) {
            throw new MissingPrivilegeException(['document.viewer']);
        }

        $config = $request->query->get('config');
        $config = \is_string($config) ? json_decode($config, true, 512, \JSON_THROW_ON_ERROR) : [];

        $fileType = $request->query->getAlnum('fileType', FileTypes::PDF);
        $download = $request->query->getBoolean('download');
        $referencedDocumentId = $request->query->getAlnum('referencedDocumentId');

        $operation = new DocumentGenerateOperation($orderId, $fileType, $config, $referencedDocumentId, false, true);

        $generatedDocument = $this->documentGenerator->preview($documentTypeName, $operation, $deepLinkCode, $context);

        return $this->createResponse(
            $generatedDocument->getName(),
            $generatedDocument->getContent(),
            $download,
            $generatedDocument->getContentType()
        );
    }

    /**
     * @Since("6.4.11.0")
     * @OA\Get(
     *     path="/_action/order/document/download",
     *     summary="Download a documents",
     *     description="Download a multiple documents in one pdf file.",
     *     operationId="downloadDocuments",
     *     tags={"Admin API", "Document Management"},
     *     @OA\Parameter(
     *         name="documentIds",
     *         description="A list of document ids to download.",
     *         @OA\Schema(type="array",
     *               @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *         ),
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The documents.",
     *          @OA\MediaType(
     *              mediaType="application/octet-stream",
     *              @OA\Schema(
     *                  type="string",
     *                  format="binary"
     *              )
     *          )
     *     )
     * )
     * @Route("/api/_action/order/document/download", name="api.action.download.documents", methods={"POST"}, defaults={"_acl"={"document.viewer"}})
     */
    public function downloadDocuments(Request $request, Context $context): Response
    {
        $documentIds = $request->get('documentIds', []);

        if (!\is_array($documentIds) || empty($documentIds)) {
            throw new InvalidRequestParameterException('documentIds');
        }

        $download = $request->query->getBoolean('download', true);
        $combinedDocument = $this->documentMerger->mergeDocuments($documentIds, $context);

        if ($combinedDocument === null) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        return $this->createResponse(
            $combinedDocument->getName(),
            $combinedDocument->getContent(),
            $download,
            $combinedDocument->getContentType()
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
