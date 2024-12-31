<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tfpdf\Fpdi;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
final class DocumentMerger
{
    /**
     * @internal
     *
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private readonly EntityRepository $documentRepository,
        private readonly MediaService $mediaService,
        private readonly DocumentGenerator $documentGenerator,
        private readonly Fpdi $fpdi,
        private readonly Connection $connection
    ) {
    }

    /**
     * @param array<string> $documentIds
     */
    public function merge(array $documentIds, Context $context, string $fileType = FileTypes::PDF): ?RenderedDocument
    {
        if (empty($documentIds)) {
            return null;
        }

        $criteria = new Criteria($documentIds);
        $criteria->addAssociation('documentType');
        $criteria->addSorting(new FieldSorting('order.orderNumber'));

        /** @var DocumentCollection $documents */
        $documents = $this->documentRepository->search($criteria, $context)->getEntities();

        if ($documents->count() === 0) {
            return null;
        }

        $fileName = Random::getAlphanumericString(32) . '.' . PdfRenderer::FILE_EXTENSION;
        $renderedDocument = new RenderedDocument('', '', $fileName);

        if ($documents->count() === 1) {
            $document = $documents->first();
            if ($document === null) {
                return null;
            }

            $documentMediaId = $this->ensureDocumentMediaFileGenerated($document, $fileType, $context);
            if ($documentMediaId === null) {
                return null;
            }

            $fileBlob = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->loadFile($documentMediaId, $context));
            $renderedDocument->setContent($fileBlob);

            if ($fileType === FileTypes::HTML) {
                $renderedDocument->setContentType(RenderedDocument::HTML_CONTENT_TYPE);
                $renderedDocument->setFileExtension(FileTypes::HTML);
                $renderedDocument->setName(Random::getAlphanumericString(32) . '.' . $fileType);

                return $renderedDocument;
            }

            return $renderedDocument;
        }

        $totalPage = 0;

        $zipFileName = Random::getAlphanumericString(32) . '.zip';
        $zipFilePath = sys_get_temp_dir() . '/' . $zipFileName; // Temporary ZIP file path

        $zip = new \ZipArchive();
        $zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($documents as $document) {
            $documentMediaId = $this->ensureDocumentMediaFileGenerated($document, $fileType, $context);
            if ($documentMediaId === null) {
                continue;
            }

            // add HTML file to ZIP archive
            if ($fileType === FileTypes::HTML) {
                $content = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->loadFile($documentMediaId, $context));
                $zip->addFromString(
                    $document->getDocumentType()?->getTechnicalName() . '_' . $document->getDocumentNumber() . '.' . $fileType,
                    $content,
                );

                continue;
            }

            $config = DocumentConfigurationFactory::createConfiguration($document->getConfig());

            $media = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->loadFileStream($documentMediaId, $context)->getContents());

            $numPages = $this->fpdi->setSourceFile(StreamReader::createByString($media));

            $totalPage += $numPages;
            for ($i = 1; $i <= $numPages; ++$i) {
                $template = $this->fpdi->importPage($i);
                $size = $this->fpdi->getTemplateSize($template);
                if (!\is_array($size)) {
                    continue;
                }
                $this->fpdi->AddPage($config->getPageOrientation() ?? 'portrait', $config->getPageSize());
                $this->fpdi->useTemplate($template);
            }
        }

        if ($zip->numFiles > 0) {
            $zip->close();

            $renderedDocument = new RenderedDocument(
                '',
                '',
                $zipFileName,
                'zip',
                [],
                'application/zip',
            );
            $renderedDocument->setContent(file_get_contents($zipFilePath) ?: '');

            unlink($zipFilePath);

            return $renderedDocument;
        }

        if ($totalPage === 0) {
            return null;
        }

        $renderedDocument->setContent($this->fpdi->Output($fileName, 'S'));
        $renderedDocument->setContentType(PdfRenderer::FILE_CONTENT_TYPE);

        return $renderedDocument;
    }

    private function ensureDocumentMediaFileGenerated(DocumentEntity $document, string $fileType, Context $context): ?string
    {
        $documentMediaId = $this->loadMediaByFileExtension($document->getDocumentMediaFileIds() ?: [], $fileType);
        if ($documentMediaId !== null || $document->isStatic()) {
            return $documentMediaId;
        }

        $operation = new DocumentGenerateOperation(
            $document->getOrderId(),
            $fileType,
            $document->getConfig(),
            $document->getReferencedDocumentId()
        );

        $operation->setDocumentId($document->getId());

        $documentType = $document->getDocumentType();
        if ($documentType === null) {
            return null;
        }

        $documentStruct = $this->documentGenerator->generate(
            $documentType->getTechnicalName(),
            [$document->getOrderId() => $operation],
            $context
        )->getSuccess()->first();

        if ($documentStruct === null) {
            return null;
        }

        // need to check media
        $documentMediaId = $documentStruct->getMediaId();
        $document->setDocumentMediaFileId($documentMediaId);

        return $documentMediaId;
    }

    /**
     * @param array<string> $mediaIds
     */
    private function loadMediaByFileExtension(array $mediaIds, string $fileExtension): ?string
    {
        /** @var array{fileExtension: string, fileName: string, id: string, mimeType: string}[] $data */
        $data = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, file_name as fileName, file_extension as fileExtension, mime_type as mimeType FROM media WHERE id IN (:ids) AND file_extension = :fileExtension',
            ['ids' => Uuid::fromHexToBytesList($mediaIds), 'fileExtension' => $fileExtension],
            ['ids' => ArrayParameterType::STRING],
        );

        return empty($data) ? null : $data[0]['id'];
    }
}
