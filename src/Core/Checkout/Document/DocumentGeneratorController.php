<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class DocumentGeneratorController extends AbstractController
{
    /**
     * @var DocumentService
     */
    protected $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/order/{orderId}/document/{documentTypeName}",
     *     summary="Create a document for an order",
     *     description="Creates a document for an order. A document can for example be an invoice or a delivery note.",
     *     operationId="createDocument",
     *     tags={"Admin API", "Document Management"},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="config",
     *                 description="Additional configuration. At least a unique `documentNumber` should be provided",
     *                 type="array",
     *                 @OA\Items(type="object",
     *                      @OA\Property(
     *                          property="documentNumber",
     *                          description="A unique incremental number for the document. The `api.action.number-range.reserve` route can be used to fetch a free number. The type parameter for that route should match the document type, for example `document_invoice`, check the `number_range_type` entity for more types.",
     *                          type="string"
     *                      ),
     *                      @OA\AdditionalProperties(type="string")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="referenced_document_id",
     *                 description="Identifier of the reverenced document.",
     *                 @OA\Schema(type="string", pattern="^[0-9a-f]{32}$", default="null")
     *             ),
     *             @OA\Property(
     *                 property="static",
     *                 description="Static has to be set to `true` if a custom document is uploaded. This prevents the generation of a document and links to the uploaded media file",
     *                 @OA\Schema(type="boolean", default="false")
     *             )
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="orderId",
     *         description="Identifier of the order the document should be generated for",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="documentTypeName",
     *         description="`technicalName` of the document type. Available types can be fetched with the `/api/document-type` endpoint.",
     *         @OA\Schema(type="string"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="fileType",
     *         description="Filetype of the document beeing created",
     *         @OA\Schema(type="string", default="pdf"),
     *         in="query",
     *     ),
     *      @OA\Response(
     *          response="200",
     *          description="Document created successfully. The `api/_action/document/{documentId}/{deepLinkCode}` route can be used to download the document.",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="documentId",
     *                  description="Identifier of the document.",
     *                  type="string",
     *                  pattern="^[0-9a-f]{32}$"),
     *              @OA\Property(
     *                  property="documentDeepLink",
     *                  description="A unique hash code which is required to open the document.",
     *                  type="string"),
     *          )
     *     )
     * )
     *
     * @Route("/api/_action/order/{orderId}/document/{documentTypeName}", name="api.action.document.invoice", methods={"POST"})
     *
     * @throws InvalidDocumentGeneratorTypeException
     * @throws InvalidFileGeneratorTypeException
     */
    public function createDocument(Request $request, string $orderId, string $documentTypeName, Context $context): JsonResponse
    {
        $fileType = $request->query->getAlnum('fileType', FileTypes::PDF);
        $config = DocumentConfigurationFactory::createConfiguration($request->request->all('config'));
        $referencedDocumentId = $request->request->get('referenced_document_id');
        if ($referencedDocumentId !== null && !\is_string($referencedDocumentId)) {
            throw new InvalidRequestParameterException('referenced_document_id');
        }

        $documentIdStruct = $this->documentService->create(
            $orderId,
            $documentTypeName,
            $fileType,
            $config,
            $context,
            $referencedDocumentId,
            (bool) $request->request->get('static', false)
        );

        return new JsonResponse(
            [
                'documentId' => $documentIdStruct->getId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
            ]
        );
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/document/{documentId}/upload",
     *     summary="Upload a file for a document",
     *     description="Uploads a file for a document. This prevents the document from beeing dynamically generated and delivers the uploaded file instead, when the document is downloaded.

Note:
* The document is required to be `static`
* A document can only have one media file

The are two methods of providing a file to this route:
 * Use a typical file upload and provide the file in the request
 * Fetch the file from an url. This only works if the `shopware.media.enable_url_upload_feature` variable is set to true in the shop environment.
To use file upload via url, the content type has to be `application/json` and the parameter `url` has to be provided.",
     *     operationId="uploadToDocument",
     *     tags={"Admin API", "Document Management"},
     *     @OA\RequestBody(
     *          @OA\MediaType(
     *              mediaType="application/octet-stream",
     *              @OA\Schema(
     *                  type="string",
     *                  format="binary"
     *              )
     *          ),
     *          @OA\JsonContent(
     *              required={
     *                  "url"
     *              },
     *              @OA\Property(
     *                  property="url",
     *                  description="The url of the document that will be downloaded.",
     *                  type="string"
     *              )
     *          )
     *     ),
     *     @OA\Parameter(
     *         name="documentId",
     *         description="Identifier of the document the new file should be added to.",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="fileName",
     *         description="Name of the uploaded file.",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         in="query",
     *     ),
     *     @OA\Parameter(
     *         name="extension",
     *         description="Extension of the uploaded file. For example `pdf`",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         in="query",
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Document uploaded successful",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="documentId",
     *                 description="Identifier of the document.",
     *                 type="string",
     *                 pattern="^[0-9a-f]{32}$"),
     *             @OA\Property(
     *                 property="documentDeepLink",
     *                 description="A unique hash code which is required to open the document.",
     *                 type="string"),
     *         )
     *     )
     * )
     * @Route("/api/_action/document/{documentId}/upload", name="api.action.document.upload", methods={"POST"})
     */
    public function uploadToDocument(Request $request, string $documentId, Context $context): JsonResponse
    {
        $documentIdStruct = $this->documentService->uploadFileForDocument(
            $documentId,
            $context,
            $request
        );

        return new JsonResponse(
            [
                'documentId' => $documentIdStruct->getId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
            ]
        );
    }
}
