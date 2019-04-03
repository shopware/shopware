<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DocumentController extends AbstractController
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
     * @Route("/api/v{version}/_action/document/{documentId}/{deepLinkCode}", defaults={"auth_required"=false}, name="api.action.download.document", methods={"GET"})
     */
    public function downloadDocument(Request $request, string $documentId, string $deepLinkCode, Context $context): Response
    {
        $download = $request->query->getBoolean('download', false);
        $document = $this->documentService->getDocumentByIdAndToken($documentId, $deepLinkCode, $context);

        $documentGenerated = $this->documentService->renderDocument($document, $context);

        return $this->createResponse($documentGenerated->getFilename(), $documentGenerated->getFileBlob(), $download);
    }

    /**
     * @Route(
     *     "/api/v{version}/_action/order/{orderId}/{deepLinkCode}/document/{documentTypeName}/preview",
     *     defaults={"auth_required"=false},
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
        $config = $request->query->has('config') ? json_decode($request->query->get('config'), true) : [];
        $documentConfig = DocumentConfigurationFactory::createConfiguration($config);

        $fileType = $request->query->getAlnum('fileType', FileTypes::PDF);
        $download = $request->query->getBoolean('download', false);

        $documentGenerated = $this->documentService->getPreview(
            $orderId,
            $deepLinkCode,
            $documentTypeName,
            $fileType,
            $documentConfig,
            $context
        );

        return $this->createResponse($documentGenerated->getFilename(), $documentGenerated->getFileBlob(), $download);
    }

    private function createResponse(string $filename, string $content, bool $forceDownload): Response
    {
        $response = new Response($content);

        $disposition = HeaderUtils::makeDisposition(
            $forceDownload ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $filename
        );

        // todo get from generator
        $response->headers->set('Content-Type', 'application/pdf');

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
