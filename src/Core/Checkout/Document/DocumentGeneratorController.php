<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @Route("/api/v{version}/_action/order/{orderId}/document/{documentTypeName}", name="api.action.document.invoice", methods={"POST"})
     *
     * @throws InvalidDocumentGeneratorTypeException
     * @throws InvalidFileGeneratorTypeException
     */
    public function createDocument(Request $request, string $orderId, string $documentTypeName, Context $context): JsonResponse
    {
        $fileType = $request->query->getAlnum('fileType', FileTypes::PDF);
        $config = DocumentConfigurationFactory::createConfiguration($request->request->get('config', []));
        $referencedDocumentId = $request->request->get('referenced_document_id', null);

        $documentIdStruct = $this->documentService->create(
            $orderId,
            $documentTypeName,
            $fileType,
            $config,
            $context,
            $referencedDocumentId,
            $request->request->get('static', false)
        );

        return new JsonResponse(
            [
                'documentId' => $documentIdStruct->getId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
            ]
        );
    }

    /**
     * @Route("/api/v{version}/_action/document/{documentId}/upload", name="api.action.document.upload", methods={"POST"})
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
