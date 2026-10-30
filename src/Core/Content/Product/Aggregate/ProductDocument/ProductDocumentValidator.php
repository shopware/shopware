<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Checks written product documents against the limits in `shopware.product_documents`.
 * Only what a write changes is checked: a file when a translation gets it, the number of documents when one is added
 * to a product, and the default-language translation of a new document. These checks run on the outcome of the write,
 * before it is executed. Duplicate files are checked after the write, against all documents of the product.
 *
 * @internal
 */
#[Package('inventory')]
final class ProductDocumentValidator implements EventSubscriberInterface
{
    public const VIOLATION_TOO_MANY_DOCUMENTS = 'PRODUCT_DOCUMENT_TOO_MANY_DOCUMENTS';

    public const VIOLATION_FILE_EXTENSION_NOT_ALLOWED = 'PRODUCT_DOCUMENT_FILE_EXTENSION_NOT_ALLOWED';

    public const VIOLATION_FILE_TOO_LARGE = 'PRODUCT_DOCUMENT_FILE_TOO_LARGE';

    public const VIOLATION_FILE_SIZE_UNKNOWN = 'PRODUCT_DOCUMENT_FILE_SIZE_UNKNOWN';

    public const VIOLATION_FILE_PRIVATE = 'PRODUCT_DOCUMENT_FILE_PRIVATE';

    public const VIOLATION_FILE_ALREADY_USED = 'PRODUCT_DOCUMENT_FILE_ALREADY_USED';

    public const VIOLATION_DEFAULT_TRANSLATION_MISSING = 'PRODUCT_DOCUMENT_DEFAULT_TRANSLATION_MISSING';

    public function __construct(
        private readonly WriteOutcome $writeOutcome,
        private readonly ProductDocumentReader $reader,
        private readonly ProductDocumentLimits $limits,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
            PostWriteValidationEvent::class => 'validateUniqueFiles',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $documents = $this->writeOutcome->touchedRows(
            $event->getCommandsForEntity(ProductDocumentDefinition::ENTITY_NAME),
            ['product_id', 'product_version_id'],
        );
        $translations = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME),
            ['media_id'],
        );

        if ($documents === [] && $translations === []) {
            return;
        }

        $violations = new ConstraintViolationList();
        $this->validateFiles($event, $translations, $violations);
        $this->validateDefaultTranslations($documents, $translations, $violations);
        $this->validateNumberOfDocuments($documents, $violations);

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * A file can be the document of a product only once. This runs after the write, which has stored the documents
     * of the product as they are now.
     */
    public function validateUniqueFiles(PostWriteValidationEvent $event): void
    {
        // where each translation that gets a file with this write is, by document and language
        $paths = [];
        foreach ($event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME) as $command) {
            if ((!$command instanceof InsertCommand && !$command instanceof UpdateCommand) || !\is_string($command->getPayload()['media_id'] ?? null)) {
                continue;
            }

            $primaryKey = $command->getPrimaryKey();
            $paths[$this->key($primaryKey['product_document_id'], $primaryKey['product_document_version_id'])][Uuid::fromBytesToHex($primaryKey['language_id'])] = $command->getPath();
        }

        if ($paths === []) {
            return;
        }

        $violations = new ConstraintViolationList();
        foreach ($this->reader->getSharedFiles(array_keys($paths)) as $translation) {
            $path = $paths[$translation['document']][$translation['languageId']] ?? null;
            if ($path === null) {
                continue;
            }

            $violations->add($this->violation(
                code: self::VIOLATION_FILE_ALREADY_USED,
                messageTemplate: 'The file "{{ mediaId }}" is already a document of this product.',
                parameters: ['{{ mediaId }}' => $translation['mediaId']],
                path: $path . '/mediaId',
            ));
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param list<OutcomeRow> $translations
     */
    private function validateFiles(PreWriteValidationEvent $event, array $translations, ConstraintViolationList $violations): void
    {
        // translations that get another file, with the file's id
        $changedFiles = [];
        foreach ($translations as $translation) {
            $mediaId = $translation->getResultingValue('media_id');
            if (\is_string($mediaId) && $mediaId !== $translation->getStoredValue('media_id')) {
                $changedFiles[] = [$translation, Uuid::fromBytesToHex($mediaId)];
            }
        }

        if ($changedFiles === []) {
            return;
        }

        $files = $this->getFiles($event, array_values(array_unique(array_column($changedFiles, 1))));
        $allowedExtensions = $this->limits->allowedExtensions;

        foreach ($changedFiles as [$translation, $mediaId]) {
            $file = $files[$mediaId] ?? null;
            if ($file === null) {
                // the foreign key rejects a file that doesn't exist
                continue;
            }

            $path = $translation->path . '/mediaId';

            if ($file['private']) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_PRIVATE,
                    messageTemplate: 'The file "{{ mediaId }}" is private, but shoppers have to be able to download the files of product documents.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $path,
                ));
            }

            // a file that has no content yet
            if ($file['size'] === null) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_SIZE_UNKNOWN,
                    messageTemplate: 'The size of the file "{{ mediaId }}" is unknown. Upload the file first.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $path,
                ));

                continue;
            }

            if (!\in_array(mb_strtolower((string) $file['extension']), $allowedExtensions, true)) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_EXTENSION_NOT_ALLOWED,
                    messageTemplate: 'The file type "{{ extension }}" is not allowed for product documents. Allowed are: {{ allowed }}.',
                    parameters: ['{{ extension }}' => (string) $file['extension'], '{{ allowed }}' => implode(', ', $allowedExtensions)],
                    path: $path,
                ));
            }

            if ($file['size'] > $this->limits->maxFileSize) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_TOO_LARGE,
                    messageTemplate: 'The file is {{ size }}, but the files of product documents can have at most {{ maxSize }}.',
                    parameters: ['{{ size }}' => MemorySizeCalculator::formatToBytes($file['size']), '{{ maxSize }}' => MemorySizeCalculator::formatToBytes($this->limits->maxFileSize)],
                    path: $path,
                ));
            }
        }
    }

    /**
     * The files as they will be after the write: a file the same write creates or changes from the write, the others
     * as stored.
     *
     * @param list<string> $mediaIds hex
     *
     * @return array<string, array{extension: string|null, size: int|null, private: bool}> keyed by media id
     */
    private function getFiles(PreWriteValidationEvent $event, array $mediaIds): array
    {
        $ids = array_flip(Uuid::fromHexToBytesList($mediaIds));
        $mediaCommands = array_values(array_filter(
            $event->getCommandsForEntity(MediaDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => isset($ids[$command->getPrimaryKey()['id'] ?? '']),
        ));

        $files = [];
        if ($mediaCommands !== []) {
            foreach ($this->writeOutcome->resultingRows($mediaCommands, ['file_extension', 'file_size', 'private']) as $media) {
                $extension = $media->getResultingValue('file_extension');
                $size = $media->getResultingValue('file_size');

                $files[Uuid::fromBytesToHex($media->primaryKey['id'])] = [
                    'extension' => \is_string($extension) ? $extension : null,
                    'size' => is_numeric($size) ? (int) $size : null,
                    'private' => (bool) $media->getResultingValue('private'),
                ];
            }
        }

        return $files + $this->reader->getFiles(array_values(array_diff($mediaIds, array_keys($files))));
    }

    /**
     * A new document needs a file in the default language, which other languages fall back to. An existing document
     * can't lose it: its default-language translation can't be deleted, and the file of that translation is required.
     *
     * @param list<OutcomeRow> $documents
     * @param list<OutcomeRow> $translations
     */
    private function validateDefaultTranslations(array $documents, array $translations, ConstraintViolationList $violations): void
    {
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $withDefaultFile = [];
        foreach ($translations as $translation) {
            if ($translation->primaryKey['language_id'] === $systemLanguageId && \is_string($translation->getResultingValue('media_id'))) {
                $withDefaultFile[$this->key($translation->primaryKey['product_document_id'], $translation->primaryKey['product_document_version_id'])] = true;
            }
        }

        foreach ($documents as $document) {
            $documentKey = $this->key($document->primaryKey['id'], $document->primaryKey['version_id']);
            if (!$document->isNew || isset($withDefaultFile[$documentKey])) {
                continue;
            }

            $violations->add($this->violation(
                code: self::VIOLATION_DEFAULT_TRANSLATION_MISSING,
                messageTemplate: 'The product document "{{ documentId }}" has no file in the default language, which other languages fall back to.',
                parameters: ['{{ documentId }}' => Uuid::fromBytesToHex($document->primaryKey['id'])],
                path: $document->path,
            ));
        }
    }

    /**
     * Each document exists in the default language, so the default language always has the most documents of a product.
     *
     * @param list<OutcomeRow> $documents
     */
    private function validateNumberOfDocuments(array $documents, ConstraintViolationList $violations): void
    {
        // per product, the documents the write adds to it in write order, and how many it takes away
        $added = [];
        $removed = [];
        foreach ($documents as $document) {
            $before = $this->productKey($document->getStoredValue('product_id'), $document->getStoredValue('product_version_id'));
            $after = $this->productKey($document->getResultingValue('product_id'), $document->getResultingValue('product_version_id'));
            if ($before === $after) {
                continue;
            }

            if ($before !== null) {
                $removed[$before] = ($removed[$before] ?? 0) + 1;
            }

            if ($after !== null) {
                $added[$after][] = $document;
            }
        }

        if ($added === []) {
            return;
        }

        $stored = $this->reader->countDocuments(array_keys($added));

        foreach ($added as $product => $addedDocuments) {
            $tooMany = ($stored[$product] ?? 0) - ($removed[$product] ?? 0) + \count($addedDocuments) - $this->limits->maxPerLanguage;
            if ($tooMany <= 0) {
                continue;
            }

            // the documents added last are the ones that don't fit anymore
            foreach (\array_slice($addedDocuments, -$tooMany) as $document) {
                $violations->add($this->violation(
                    code: self::VIOLATION_TOO_MANY_DOCUMENTS,
                    messageTemplate: 'A product can have at most {{ max }} documents per language.',
                    parameters: ['{{ max }}' => (string) $this->limits->maxPerLanguage],
                    path: $document->path,
                ));
            }
        }
    }

    private function productKey(mixed $productId, mixed $versionId): ?string
    {
        if (!\is_string($productId) || !\is_string($versionId)) {
            return null;
        }

        return $this->key($productId, $versionId);
    }

    /**
     * An id and a version id, as ProductDocumentReader keys products and documents.
     */
    private function key(string $id, string $versionId): string
    {
        return Uuid::fromBytesToHex($id) . Uuid::fromBytesToHex($versionId);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function violation(string $code, string $messageTemplate, array $parameters, string $path): ConstraintViolation
    {
        return new ConstraintViolation(
            message: strtr($messageTemplate, $parameters),
            messageTemplate: $messageTemplate,
            parameters: $parameters,
            root: null,
            propertyPath: $path,
            invalidValue: null,
            code: $code,
        );
    }
}
