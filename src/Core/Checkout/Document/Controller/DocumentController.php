<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Controller;

use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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

    private EntityRepository $documentRepository;

    /**
     * @internal
     */
    public function __construct(
        DocumentService $documentService,
        DocumentGenerator $documentGenerator,
        DocumentMerger $documentMerger,
        EntityRepository $documentRepository
    ) {
        $this->documentService = $documentService;
        $this->documentGenerator = $documentGenerator;
        $this->documentMerger = $documentMerger;
        $this->documentRepository = $documentRepository;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/document/{documentId}/{deepLinkCode}", name="api.action.download.document", methods={"GET"})
     */
    public function downloadDocument(Request $request, string $documentId, string $deepLinkCode, Context $context): Response
    {
        if (Feature::isActive('v6.5.0.0')) {
            /**
             * @deprecated tag:v6.5.0 - Put the check on the annotation instead: defaults={"_acl"={"document.viewer"}}
             */
            if (!$context->isAllowed('document.viewer')) {
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

        $download = $request->query->getBoolean('download');

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
        if (Feature::isActive('v6.5.0.0')) {
            /**
             * @deprecated tag:v6.5.0 - Put the check on the annotation instead: defaults={"_acl"={"document.viewer"}}
             */
            if (!$context->isAllowed('document.viewer')) {
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

        $config = $request->query->get('config');
        $config = \is_string($config) ? json_decode($config, true, 512, \JSON_THROW_ON_ERROR) : [];
        $documentConfig = DocumentConfigurationFactory::createConfiguration($config);

        $fileType = $request->query->getAlnum('fileType', FileTypes::PDF);
        $download = $request->query->getBoolean('download');

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

    /**
     * @Since("6.4.12.0")
     * @Route("/api/_action/order/document/download", name="api.action.download.documents", methods={"POST"}, defaults={"_acl"={"document.viewer"}})
     */
    public function downloadDocuments(Request $request, Context $context): Response
    {
        $documentIds = $request->get('documentIds', []);

        if (!\is_array($documentIds) || empty($documentIds)) {
            throw new InvalidRequestParameterException('documentIds');
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
