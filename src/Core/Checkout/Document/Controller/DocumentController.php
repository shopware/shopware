<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Controller;

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
     * @Route("/api/v{version}/_action/document/{documentId}/{deepLinkCode}", defaults={"auth_required"=false}, name="api.action.download.document", methods={"GET"})
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
            $filename
        );

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
