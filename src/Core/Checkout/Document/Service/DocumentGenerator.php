<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
#[Package('after-sales')]
class DocumentGenerator
{
    /**
     * @internal
     *
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private readonly DocumentRendererRegistry $rendererRegistry,
        private readonly DocumentFileRendererRegistry $fileRendererRegistry,
        private readonly MediaService $mediaService,
        private readonly EntityRepository $documentRepository,
        private readonly Connection $connection
    ) {
    }

    /**
     * @deprecated tag:v6.7.0 - Parameter $fileType will be added - reason:new-optional-parameter
     */
    public function readDocument(
        string $documentId,
        Context $context,
        string $deepLinkCode = '',
        /* , string $fileType = PdfRenderer::FILE_EXTENSION */
    ): ?RenderedDocument {
        $fileType = \func_get_args()[3] ?? PdfRenderer::FILE_EXTENSION;

        $criteria = new Criteria([$documentId]);

        if ($deepLinkCode !== '') {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));
        }

        $criteria->addAssociations([
            'documentMediaFile',
            'documentType',
            'documentA11yMediaFile',
        ]);

        $document = $this->documentRepository->search($criteria, $context)->get($documentId);
        if (!$document instanceof DocumentEntity) {
            throw DocumentException::documentNotFound($documentId);
        }

        $document = $this->ensureDocumentMediaFileGenerated($document, $fileType, $context);
        $documentMedia = $this->loadMediaByFileType($document, $fileType);
        if (!$documentMedia) {
            return null;
        }

        $fileBlob = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->loadFile($documentMedia->getId(), $context));

        $renderedDocument = new RenderedDocument(
            name: $documentMedia->getFileName() . '.' . $documentMedia->getFileExtension(),
            fileExtension: $documentMedia->getFileExtension() ?? $fileType,
            contentType: $documentMedia->getMimeType()
        );
        $renderedDocument->setContent($fileBlob);

        return $renderedDocument;
    }

    public function preview(string $documentType, DocumentGenerateOperation $operation, string $deepLinkCode, Context $context): RenderedDocument
    {
        $config = new DocumentRendererConfig();
        $config->deepLinkCode = $deepLinkCode;

        if (!empty($operation->getConfig()['custom']['invoiceNumber'])) {
            $invoiceNumber = (string) $operation->getConfig()['custom']['invoiceNumber'];
            $operation->setReferencedDocumentId($this->getReferenceId($operation->getOrderId(), $invoiceNumber));
        }

        $rendered = $this->rendererRegistry->render($documentType, [$operation->getOrderId() => $operation], $context, $config);
        $document = $rendered->getOrderSuccess($operation->getOrderId());

        if (!$document instanceof RenderedDocument) {
            throw DocumentException::generationError($rendered->getOrderError($operation->getOrderId())?->getMessage());
        }

        if (!Feature::isActive('v6.7.0.0')) {
            $document->setContent($this->fileRendererRegistry->render($document));
        }

        return $document;
    }

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    public function generate(string $documentType, array $operations, Context $context): DocumentGenerationResult
    {
        $documentTypeId = $this->getDocumentTypeByName($documentType);

        if ($documentTypeId === null) {
            throw DocumentException::invalidDocumentRenderer($documentType);
        }

        $rendered = $this->rendererRegistry->render($documentType, $operations, $context, new DocumentRendererConfig());

        $result = new DocumentGenerationResult();

        foreach ($rendered->getErrors() as $orderId => $error) {
            $result->addError($orderId, $error);
        }

        $records = [];

        $success = $rendered->getSuccess();

        foreach ($operations as $orderId => $operation) {
            try {
                $document = $success[$orderId] ?? null;

                if (!($document instanceof RenderedDocument)) {
                    continue;
                }

                $this->checkDocumentNumberAlreadyExits($documentType, $document->getNumber(), $operation->getDocumentId());

                $deepLinkCode = Random::getAlphanumericString(32);
                $id = $operation->getDocumentId() ?? Uuid::randomHex();

                $mediaId = $this->resolveMediaId($operation, $context, $document);
                $mediaIdForHtmlA11y = $this->resolveMediaIdForA11y($operation, $context, $document);

                $records[] = [
                    'id' => $id,
                    'documentTypeId' => $documentTypeId,
                    'fileType' => $operation->getFileType(),
                    'orderId' => $orderId,
                    'orderVersionId' => $operation->getOrderVersionId(),
                    'static' => $operation->isStatic(),
                    'documentMediaFileId' => $mediaId,
                    'config' => $document->getConfig(),
                    'deepLinkCode' => $deepLinkCode,
                    'referencedDocumentId' => $operation->getReferencedDocumentId(),
                    'documentA11yMediaFileId' => $mediaIdForHtmlA11y,
                ];

                $result->addSuccess(new DocumentIdStruct($id, $deepLinkCode, $mediaId, $mediaIdForHtmlA11y));
            } catch (\Throwable $exception) {
                $result->addError($orderId, $exception);
            }
        }

        $this->writeRecords($records, $context);

        return $result;
    }

    public function upload(string $documentId, Context $context, Request $uploadedFileRequest): DocumentIdStruct
    {
        $criteria = new Criteria([$documentId]);
        $criteria->addAssociation('documentMediaFile');

        $document = $this->documentRepository->search($criteria, $context)->first();
        if (!($document instanceof DocumentEntity)) {
            throw DocumentException::documentNotFound($documentId);
        }

        $documentMedia = $document->getDocumentMediaFile();
        if ($documentMedia?->getId() !== null) {
            throw DocumentException::documentGenerationException('Document already exists');
        }

        if ($document->isStatic() === false) {
            throw DocumentException::documentGenerationException('This document is dynamically generated and cannot be overwritten');
        }

        $mediaFile = $this->mediaService->fetchFile($uploadedFileRequest);

        $fileName = (string) $uploadedFileRequest->query->get('fileName');

        if ($fileName === '') {
            throw DocumentException::documentGenerationException('Parameter "fileName" is missing');
        }

        $mediaId = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->saveMediaFile($mediaFile, $fileName, $context, 'document'));

        $this->documentRepository->upsert([
            [
                'id' => $documentId,
                'documentMediaFileId' => $mediaId,
                'documentA11yMediaFileId' => null,
                'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ], $context);

        return new DocumentIdStruct($documentId, $document->getDeepLinkCode(), $mediaId);
    }

    /**
     * @param array<mixed> $records
     */
    private function writeRecords(array $records, Context $context): void
    {
        if (empty($records)) {
            return;
        }

        $this->documentRepository->upsert($records, $context);
    }

    private function getDocumentTypeByName(string $documentType): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) as id FROM document_type WHERE technical_name = :technicalName',
            ['technicalName' => $documentType]
        );

        return $id ?: null;
    }

    private function checkDocumentNumberAlreadyExits(
        string $documentTypeName,
        string $documentNumber,
        ?string $documentId = null
    ): void {
        $sql = '
            SELECT COUNT(id)
            FROM document
            WHERE
                document_type_id IN (
                    SELECT id
                    FROM document_type
                    WHERE technical_name = :documentTypeName
                )
                AND document_number = :documentNumber
                AND id ' . ($documentId !== null ? '!= :documentId' : 'IS NOT NULL') . '
            LIMIT 1
        ';

        $params = [
            'documentTypeName' => $documentTypeName,
            'documentNumber' => $documentNumber,
        ];

        if ($documentId !== null) {
            $params['documentId'] = Uuid::fromHexToBytes($documentId);
        }

        $statement = $this->connection->executeQuery($sql, $params);

        $result = (bool) $statement->fetchOne();

        if ($result) {
            throw DocumentException::documentNumberAlreadyExistsException($documentNumber);
        }
    }

    private function ensureDocumentMediaFileGenerated(DocumentEntity $document, string $fileType, Context $context): ?DocumentEntity
    {
        $documentMedia = $this->loadMediaByFileType($document, $fileType);
        if ($documentMedia?->getId() !== null || $document->isStatic()) {
            return $document;
        }

        $documentId = $document->getId();

        $operation = new DocumentGenerateOperation(
            $document->getOrderId(),
            $fileType,
            $document->getConfig(),
            $document->getReferencedDocumentId()
        );

        $operation->setDocumentId($documentId);

        /** @var DocumentTypeEntity $documentType */
        $documentType = $document->getDocumentType();

        $documentStruct = $this->generate(
            $documentType->getTechnicalName(),
            [$document->getOrderId() => $operation],
            $context
        )->getSuccess()->first();

        if ($documentStruct === null) {
            return $document;
        }

        // Fetch the document again because new mediaFile is generated
        $criteria = new Criteria([$documentId]);

        $criteria->addAssociation('documentMediaFile')
            ->addAssociation('documentA11yMediaFile')
            ->addAssociation('documentType');

        /** @var ?DocumentEntity $document */
        $document = $this->documentRepository->search($criteria, $context)->get($documentId);

        return $document;
    }

    private function resolveMediaId(DocumentGenerateOperation $operation, Context $context, RenderedDocument $document): ?string
    {
        if ($operation->isStatic()) {
            return null;
        }

        try {
            $blob = $this->fileRendererRegistry->render($document);
        } catch (\Throwable) {
            return null;
        }

        if ($blob === '') {
            return null;
        }

        return $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->saveFile(
            $blob,
            $document->getFileExtension(),
            $document->getContentType(),
            $document->getName(),
            $context,
            'document'
        ));
    }

    private function getReferenceId(string $orderId, string $invoiceNumber): string
    {
        return (string) $this->connection->fetchOne('
            SELECT LOWER(HEX(document.id))
            FROM document INNER JOIN document_type
                ON document.document_type_id = document_type.id
            WHERE document_type.technical_name = :technicalName
            AND document.document_number = :invoiceNumber
            AND document.order_id = :orderId
        ', [
            'technicalName' => InvoiceRenderer::TYPE,
            'invoiceNumber' => $invoiceNumber,
            'orderId' => Uuid::fromHexToBytes($orderId),
        ]);
    }

    private function resolveMediaIdForA11y(DocumentGenerateOperation $operation, Context $context, RenderedDocument $document): ?string
    {
        $document = clone $document;
        $document->setContentType(HtmlRenderer::FILE_CONTENT_TYPE);
        $document->setFileExtension(HtmlRenderer::FILE_EXTENSION);

        return $this->resolveMediaId($operation, $context, $document);
    }

    private function loadMediaByFileType(?DocumentEntity $document, string $fileType): ?MediaEntity
    {
        $medias = array_filter([
            $document?->getDocumentMediaFile(),
            $document?->getDocumentA11yMediaFile(),
        ], fn (?MediaEntity $media) => $media?->getFileExtension() === strtolower($fileType));

        return array_shift($medias) ?? null;
    }
}
