<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use setasign\Fpdi\FpdiException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tfpdf\Fpdi;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

#[Package('after-sales')]
final class DocumentMerger
{
    /**
     * Cache of document media file IDs indexed by document ID
     *
     * @var array<string, string>
     */
    private array $documentMediaCache = [];

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
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @param array<string> $documentIds
     */
    public function merge(array $documentIds, Context $context): ?RenderedDocument
    {
        if ($documentIds === []) {
            return null;
        }

        $documents = $this->prepareDocumentsForMerge($documentIds, $context);

        if ($documents->count() === 0) {
            return null;
        }

        if ($documents->count() === 1) {
            $document = $documents->first();
            if ($document === null) {
                return null;
            }

            $documentMediaId = $this->documentMediaCache[$document->getId()] ?? null;
            if ($documentMediaId === null) {
                return null;
            }

            $fileBlob = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->loadFile($documentMediaId, $context));

            return $this->createRenderedDocument($document, $fileBlob);
        }

        if (!$this->containsOnlyPdfs($documents)) {
            return $this->createDocumentsZip($documents, $context);
        }

        try {
            $fileName = Random::getAlphanumericString(32) . '.' . PdfRenderer::FILE_EXTENSION;
            $renderedDocument = new RenderedDocument(name: $fileName);

            return $this->mergeWithFpdi($documents, $context, $renderedDocument);
        } catch (FpdiException $e) {
            return $this->createDocumentsZip($documents, $context);
        }
    }

    private function createRenderedDocument(DocumentEntity $document, string $fileBlob): RenderedDocument
    {
        $fileExtension = $this->resolveFileType($document);
        $fileName = $document->getDocumentMediaFile()?->getFileName() ?? Random::getAlphanumericString(32);
        $contentType = $document->getDocumentMediaFile()?->getMimeType() ?? $this->getContentType($fileExtension);

        $renderedDocument = new RenderedDocument(
            name: $fileName . '.' . $fileExtension,
            fileExtension: $fileExtension,
            contentType: $contentType,
        );
        $renderedDocument->setContent($fileBlob);

        return $renderedDocument;
    }

    private function containsOnlyPdfs(DocumentCollection $documents): bool
    {
        foreach ($documents as $document) {
            if ($this->resolveFileType($document) !== PdfRenderer::FILE_EXTENSION) {
                return false;
            }
        }

        return true;
    }

    private function mergeWithFpdi(DocumentCollection $documents, Context $context, RenderedDocument $renderedDocument): ?RenderedDocument
    {
        $totalPage = 0;

        foreach ($documents as $document) {
            $documentMediaId = $this->documentMediaCache[$document->getId()] ?? null;
            if ($documentMediaId === null) {
                continue;
            }

            $media = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): string => $this->mediaService->loadFileStream($documentMediaId, $context)->getContents());

            $numPages = $this->fpdi->setSourceFile(StreamReader::createByString($media));

            $totalPage += $numPages;
            for ($i = 1; $i <= $numPages; ++$i) {
                $template = $this->fpdi->importPage($i);
                $size = $this->fpdi->getTemplateSize($template);
                if (!\is_array($size)) {
                    continue;
                }
                $this->fpdi->AddPage(
                    $size['orientation'],
                    [
                        $size[0], // width
                        $size[1], // height
                    ],
                );
                $this->fpdi->useTemplate($template);
            }
        }

        if ($totalPage === 0) {
            return null;
        }

        $renderedDocument->setContent($this->fpdi->Output($renderedDocument->getName(), 'S'));
        $renderedDocument->setContentType(PdfRenderer::FILE_CONTENT_TYPE);

        return $renderedDocument;
    }

    private function ensureDocumentMediaFileGenerated(DocumentEntity $document, Context $context): ?DocumentEntity
    {
        $documentMediaId = $document->getDocumentMediaFileId();
        if ($documentMediaId !== null || $document->isStatic()) {
            return $document;
        }

        $operation = new DocumentGenerateOperation(
            $document->getOrderId(),
            $this->resolveFileType($document),
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

        $criteria = (new Criteria([$document->getId()]))
            ->addAssociations(['documentType', 'documentMediaFile']);

        $document = $this->documentRepository->search($criteria, $context)->getEntities()->first();
        \assert($document !== null);

        return $document;
    }

    /**
     * @param array<string> $documentIds
     */
    private function prepareDocumentsForMerge(array $documentIds, Context $context): DocumentCollection
    {
        $criteria = (new Criteria($documentIds))
            ->addAssociation('documentType')
            ->addAssociation('documentMediaFile')
            ->addAssociation('order')
            ->addSorting(new FieldSorting('order.orderNumber'));

        $documents = $this->documentRepository->search($criteria, $context)->getEntities();

        $mediaCache = [];
        $preparedDocuments = [];

        foreach ($documents as $document) {
            $preparedDocument = $this->ensureDocumentMediaFileGenerated($document, $context) ?? $document;

            $preparedDocuments[] = $preparedDocument;

            $mediaId = $preparedDocument->getDocumentMediaFileId();
            if ($mediaId !== null) {
                $mediaCache[$preparedDocument->getId()] = $mediaId;
            }
        }

        $this->documentMediaCache = $mediaCache;

        return new DocumentCollection($preparedDocuments);
    }

    private function createDocumentsZip(DocumentCollection $documents, Context $context): ?RenderedDocument
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sw_documents_');
        $zip = new \ZipArchive();

        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw DocumentException::cannotCreateZipFile($tempFile);
        }

        $totalDocuments = 0;

        foreach ($documents as $document) {
            $documentMediaId = $this->documentMediaCache[$document->getId()] ?? null;
            if ($documentMediaId === null) {
                continue;
            }

            $fileContent = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($documentMediaId) {
                return $this->mediaService->loadFile($documentMediaId, $context);
            });

            $technicalName = $document->getDocumentType()?->getTechnicalName() ?? 'unknown';
            $orderNumber = $document->getOrder()?->getOrderNumber() ?? $document->getOrderId();
            $documentNumber = $document->getDocumentNumber() ?? $document->getId();
            $name = $orderNumber . '_' . $technicalName . '_' . $documentNumber . '.' . $this->resolveFileType($document);

            $zip->addFromString($name, $fileContent);

            ++$totalDocuments;
        }

        $zip->close();

        if ($totalDocuments === 0) {
            $this->filesystem->remove($tempFile);

            return null;
        }

        $fileName = Random::getAlphanumericString(32) . '.zip';

        $renderedDocument = new RenderedDocument(
            name: $fileName,
            fileExtension: 'zip',
            contentType: 'application/zip'
        );

        try {
            $fileContent = $this->filesystem->readFile($tempFile);
            $renderedDocument->setContent($fileContent);

            return $renderedDocument;
        } catch (IOException $e) {
            throw DocumentException::cannotReadZipFile($tempFile, $e);
        } finally {
            if ($this->filesystem->exists($tempFile)) {
                $this->filesystem->remove($tempFile);
            }
        }
    }

    private function resolveFileType(DocumentEntity $document): string
    {
        $fileExtension = $document->getDocumentMediaFile()?->getFileExtension();
        if (\is_string($fileExtension) && $fileExtension !== '') {
            return $fileExtension;
        }

        $fileTypes = $document->getConfig()['fileTypes'] ?? null;
        if (\is_array($fileTypes) && isset($fileTypes[0]) && \is_string($fileTypes[0]) && $fileTypes[0] !== '') {
            return $fileTypes[0];
        }

        return PdfRenderer::FILE_EXTENSION;
    }

    private function getContentType(string $fileExtension): string
    {
        return match ($fileExtension) {
            'xml' => 'application/xml',
            default => PdfRenderer::FILE_CONTENT_TYPE,
        };
    }
}
