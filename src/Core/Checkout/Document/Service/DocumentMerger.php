<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Psr\Clock\ClockInterface;
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
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

#[Package('after-sales')]
final class DocumentMerger
{
    /**
     * Fallback name for the merged file if the merged documents do not share a single document type
     */
    private const MIXED_DOCUMENT_TYPES_FILE_NAME = 'documents';

    private const MAX_FILE_NAME_LENGTH = 100;

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
        private readonly ClockInterface $clock,
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
            $fileName = $this->buildFileName($documents) . '.' . PdfRenderer::FILE_EXTENSION;
            $renderedDocument = new RenderedDocument(name: $fileName);

            return $this->mergeWithFpdi($documents, $context, $renderedDocument);
        } catch (FpdiException $e) {
            return $this->createDocumentsZip($documents, $context);
        }
    }

    private function createRenderedDocument(DocumentEntity $document, string $fileBlob): RenderedDocument
    {
        $fileExtension = $this->resolveFileType($document);
        $fileName = $document->getDocumentMediaFile()?->getFileName() ?? $this->buildFileName(new DocumentCollection([$document]));
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

    /**
     * Builds a human readable file name without the file extension, e.g. `invoice_10000` for a single document or
     * `delivery_note_2026-09-10` for multiple merged documents of the same document type.
     */
    private function buildFileName(DocumentCollection $documents): string
    {
        if ($documents->count() === 1) {
            $document = $documents->first();
            $documentName = $document !== null ? $this->getDocumentName($document) : null;

            if ($documentName !== null) {
                return $documentName;
            }
        }

        $date = $this->clock->now()->format('Y-m-d');

        return $this->getSharedFileNamePrefix($documents) . '_' . $date;
    }

    /**
     * Resolves the name the document was rendered with, so that downloading a single document always yields the
     * same file name, no matter whether it is downloaded on its own or via the merge endpoint.
     */
    private function getDocumentName(DocumentEntity $document): ?string
    {
        $config = $document->getConfig();

        $documentNumber = $this->getConfigValue($config, 'documentNumber');
        if ($documentNumber === '') {
            $documentNumber = $document->getDocumentNumber() ?? '';
        }

        if ($documentNumber === '') {
            return null;
        }

        $prefix = $this->getConfigValue($config, 'filenamePrefix');
        $suffix = $this->getConfigValue($config, 'filenameSuffix');

        if ($prefix === '' && $suffix === '') {
            // Without a configured prefix the document number alone would not describe the content of the file
            $typeName = $document->getTypeName();
            $prefix = $typeName !== null ? $typeName . '_' : '';
        }

        $name = $this->sanitizeFileName($prefix . $documentNumber . $suffix);

        return $name !== '' ? $name : null;
    }

    /**
     * Documents downloaded via the merge endpoint are usually grouped by document type, so the file name prefix that
     * is configured for that document type describes the content of the merged file best.
     */
    private function getSharedFileNamePrefix(DocumentCollection $documents): string
    {
        $typeNames = [];
        $prefixes = [];

        foreach ($documents as $document) {
            $typeName = $this->sanitizeFileName($document->getTypeName() ?? '');
            if ($typeName !== '') {
                $typeNames[] = $typeName;
            }

            $prefix = $this->sanitizeFileName($this->getConfigValue($document->getConfig(), 'filenamePrefix'));
            if ($prefix !== '') {
                $prefixes[] = $prefix;
            }
        }

        $typeNames = array_values(array_unique($typeNames));
        $prefixes = array_values(array_unique($prefixes));

        if (\count($typeNames) !== 1) {
            return self::MIXED_DOCUMENT_TYPES_FILE_NAME;
        }

        if (\count($prefixes) === 1) {
            return $prefixes[0];
        }

        return $typeNames[0];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function getConfigValue(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    private function sanitizeFileName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name) ?? '';

        return trim(mb_substr($name, 0, self::MAX_FILE_NAME_LENGTH), '-._');
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

        $fileName = $this->buildFileName($documents) . '.zip';

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
