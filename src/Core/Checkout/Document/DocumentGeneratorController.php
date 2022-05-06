<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class DocumentGeneratorController extends AbstractController
{
    protected DocumentService $documentService;

    private DocumentGenerator $documentGenerator;

    private Serializer $serializer;

    private DataValidator $dataValidator;

    /**
     * @internal
     */
    public function __construct(
        DocumentService $documentService,
        DocumentGenerator $documentGenerator,
        Serializer $serializer,
        DataValidator $dataValidator
    ) {
        $this->documentService = $documentService;
        $this->documentGenerator = $documentGenerator;
        $this->serializer = $serializer;
        $this->dataValidator = $dataValidator;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/order/{orderId}/document/{documentTypeName}",
     *     summary="Create a document for an order",
     *     deprecated=true,
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
     *                 description="Identifier of the referenced document.",
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
     * @deprecated tag:v6.5.0 - will be removed, use _action/order/document/create instead
     */
    public function createDocument(Request $request, string $orderId, string $documentTypeName, Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'will be removed - use createDocuments instead'
        );

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
            $request->request->getBoolean('static')
        );

        return new JsonResponse(
            [
                'documentId' => $documentIdStruct->getId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
            ]
        );
    }

    /**
     * @Since("6.4.11.0")
     * @OA\Post(
     *     path="_action/order/document/{documentTypeName}/create",
     *     summary="Create documents for orders",
     *     description="Creates documents for orders. Documents can for example be an invoice or a delivery note.",
     *     operationId="createDocuments",
     *     tags={"Admin API", "Document Management"},
     *     @OA\RequestBody(
     *         required=true,
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(
     *                      property="orderId",
     *                      required=true,
     *                      description="Identifier of the order.",
     *                      type="string",
     *                      pattern="^[0-9a-f]{32}$"),
     *                  @OA\Property(
     *                      property="type",
     *                      required=true,
     *                      description="Type of the document to be generated.",
     *                      type="string"),
     *                  @OA\Property(
     *                      property="fileType",
     *                      default="pdf",
     *                      description="Type of document file to be generated.",
     *                      type="string"),
     *                  @OA\Property(
     *                      property="static",
     *                      default=false,
     *                      description="Indicate if the document should be static or not.",
     *                      type="boolean"),
     *                  @OA\Property(
     *                      property="referencedDocumentId",
     *                      description="Identifier of the reverenced document.",
     *                      default="null",
     *                      type="string",
     *                      pattern="^[0-9a-f]{32}$"),
     *                  @OA\Property(
     *                      property="config",
     *                      description="Document specific configuration, like documentNumber, documentDate, documentComment.",
     *                      type="array"),
     *              )
     *          )
     *     ),
     *      @OA\Response(
     *          response="200",
     *          description="Documents created successfully. The `api/_action/order/document/create` route can be used to download the document.",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(
     *                      property="documentId",
     *                      description="Identifier of the document.",
     *                      type="string",
     *                      pattern="^[0-9a-f]{32}$"),
     *                  @OA\Property(
     *                      property="documentDeepLink",
     *                      description="A unique hash code which is required to open the document.",
     *                      type="string"),
     *              )
     *          )
     *     )
     * )
     *
     * @Route("/api/_action/order/document/{documentTypeName}/create", name="api.action.document.bulk.create", methods={"POST"}, defaults={"_acl"={"document.viewer"}})
     */
    public function createDocuments(Request $request, string $documentTypeName, Context $context): JsonResponse
    {
        $documents = $this->serializer->decode($request->getContent(), 'json');

        if (empty($documents) || !\is_array($documents)) {
            throw new InvalidRequestParameterException('Request parameters must be an array of documents object');
        }

        $operations = [];

        $definition = new DataValidationDefinition();
        $definition->addList(
            'documents',
            (new DataValidationDefinition())
                ->add('orderId', new NotBlank())
                ->add('fileType', new Choice([FileTypes::PDF]))
                ->add('config', new Type('array'))
                ->add('static', new Type('bool'))
                ->add('referencedDocumentId', new Uuid())
        );

        $this->dataValidator->validate($documents, $definition);

        foreach ($documents as $operation) {
            $operations[$operation['orderId']] = new DocumentGenerateOperation(
                $operation['orderId'],
                $operation['fileType'] ?? FileTypes::PDF,
                $operation['config'] ?? [],
                $operation['referencedDocumentId'] ?? null,
                $operation['static'] ?? false
            );
        }

        $responseData = $this->documentGenerator->generate($documentTypeName, $operations, $context);

        return new JsonResponse($responseData->map(function (DocumentIdStruct $documentIdStruct) {
            return [
                'documentId' => $documentIdStruct->getId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
            ];
        }));
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/document/{documentId}/upload",
     *     summary="Upload a file for a document",
     *     description="Uploads a file for a document. This prevents the document from being dynamically generated and delivers the uploaded file instead, when the document is downloaded.

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
        $documentIdStruct = $this->documentGenerator->upload(
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
