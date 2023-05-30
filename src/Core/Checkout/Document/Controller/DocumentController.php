<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Controller;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('customer-order')]
class DocumentController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly DocumentMerger $documentMerger
    ) {
    }

    #[Route(path: '/api/_action/document/{documentId}/{deepLinkCode}', name: 'api.action.download.document', methods: ['GET'], defaults: ['_acl' => ['document:read']])]
    public function downloadDocument(Request $request, string $documentId, string $deepLinkCode, Context $context): Response
    {
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

    #[Route(path: '/api/_action/order/{orderId}/{deepLinkCode}/document/{documentTypeName}/preview', name: 'api.action.document.preview', methods: ['GET'], defaults: ['_acl' => ['document:read']])]
    public function previewDocument(
        Request $request,
        string $orderId,
        string $deepLinkCode,
        string $documentTypeName,
        Context $context
    ): Response {
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

    #[Route(path: '/api/_action/order/document/download', name: 'api.action.download.documents', methods: ['POST'], defaults: ['_acl' => ['document:read']])]
    public function downloadDocuments(Request $request, Context $context): Response
    {
        $documentIds = $request->get('documentIds', []);

        if (!\is_array($documentIds) || empty($documentIds)) {
            throw RoutingException::invalidRequestParameter('documentIds');
        }

        $download = $request->query->getBoolean('download', true);
        $combinedDocument = $this->documentMerger->merge($documentIds, $context);

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
            preg_replace('/[\x00-\x1F\x7F-\xFF]/', '_', $filename) ?? ''
        );

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
