<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
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
 * @internal
 */
#[Package('inventory')]
final class ProductDocumentValidator implements EventSubscriberInterface
{
    public const VIOLATION_FILE_EXTENSION_NOT_ALLOWED = 'PRODUCT_DOCUMENT_FILE_EXTENSION_NOT_ALLOWED';

    public const VIOLATION_FILE_TOO_LARGE = 'PRODUCT_DOCUMENT_FILE_TOO_LARGE';

    public const VIOLATION_FILE_SIZE_UNKNOWN = 'PRODUCT_DOCUMENT_FILE_SIZE_UNKNOWN';

    public const VIOLATION_FILE_PRIVATE = 'PRODUCT_DOCUMENT_FILE_PRIVATE';

    public const VIOLATION_FILE_NOT_FOUND = 'PRODUCT_DOCUMENT_FILE_NOT_FOUND';

    public const VIOLATION_DEFAULT_LANGUAGE_FILE_MISSING = 'PRODUCT_DOCUMENT_DEFAULT_LANGUAGE_FILE_MISSING';

    private const FILE_COLUMNS = ['private', 'path', 'file_extension', 'file_size'];

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly WriteOutcome $writeOutcome,
        private readonly EntityRepository $mediaRepository,
        private readonly ProductDocumentConfig $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        if ($event->getWriteContext()->hasState(VersionManager::CLONE_SCOPE, VersionManager::MERGE_SCOPE)) {
            return;
        }

        $documents = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(ProductDocumentDefinition::ENTITY_NAME),
            ['product_id'],
        );
        $translations = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME),
            ['media_id'],
        );
        $writtenFiles = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(MediaDefinition::ENTITY_NAME),
            self::FILE_COLUMNS,
        );

        if ($documents === [] && $translations === [] && $writtenFiles === []) {
            return;
        }

        $writtenFilesById = [];
        foreach ($writtenFiles as $writtenFile) {
            $writtenFilesById[Uuid::fromBytesToHex($writtenFile->primaryKey['id'])] = $writtenFile;
        }

        $violations = new ConstraintViolationList();
        $this->validateDefaultLanguageFiles($documents, $translations, $violations);
        $this->validateFiles($event, $translations, $writtenFilesById, $violations);
        $this->validateChangedLinkedFiles($event, $writtenFilesById, $violations);

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param list<OutcomeRow> $documents
     * @param list<OutcomeRow> $translations
     */
    private function validateDefaultLanguageFiles(array $documents, array $translations, ConstraintViolationList $violations): void
    {
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $documentsWithDefaultLanguage = [];
        foreach ($translations as $translation) {
            if ($translation->primaryKey['language_id'] !== $systemLanguageId) {
                continue;
            }

            $documentsWithDefaultLanguage[$translation->primaryKey['product_document_id'] . $translation->primaryKey['product_document_version_id']] = true;

            if ($translation->getResultingValue('media_id') === null) {
                $violations->add($this->defaultLanguageFileMissing($translation->path . '/mediaId'));
            }
        }

        foreach ($documents as $document) {
            if ($document->isNew && !isset($documentsWithDefaultLanguage[$document->primaryKey['id'] . $document->primaryKey['version_id']])) {
                $violations->add($this->defaultLanguageFileMissing($document->path . '/translations/' . Defaults::LANGUAGE_SYSTEM . '/mediaId'));
            }
        }
    }

    /**
     * @param list<OutcomeRow> $translations
     * @param array<string, OutcomeRow> $writtenFilesById
     */
    private function validateFiles(PreWriteValidationEvent $event, array $translations, array $writtenFilesById, ConstraintViolationList $violations): void
    {
        $translationsWithNewFile = [];
        foreach ($translations as $translation) {
            $oldMediaId = $translation->getStoredValue('media_id');
            $newMediaId = $translation->getResultingValue('media_id');
            if (\is_string($newMediaId) && $newMediaId !== $oldMediaId) {
                $translationsWithNewFile[] = [$translation, Uuid::fromBytesToHex($newMediaId)];
            }
        }

        if ($translationsWithNewFile === []) {
            return;
        }

        $storedMediaIds = array_values(array_diff(array_column($translationsWithNewFile, 1), array_keys($writtenFilesById)));
        $files = $storedMediaIds === [] ? new MediaCollection() : $this->loadFiles($storedMediaIds, $event->getContext());
        $allowedExtensions = $this->config->getAllowedExtensions($event->getContext());

        foreach ($translationsWithNewFile as [$translation, $mediaId]) {
            $errorPath = $translation->path . '/mediaId';

            if (isset($writtenFilesById[$mediaId])) {
                $this->validateFile($mediaId, $this->writtenFile($writtenFilesById[$mediaId]), $allowedExtensions, $errorPath, $violations);

                continue;
            }

            $file = $files->get($mediaId);
            if (!$file instanceof MediaEntity) {
                // unknown or hidden from the caller
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_NOT_FOUND,
                    messageTemplate: 'The file "{{ mediaId }}" was not found.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));

                continue;
            }

            $this->validateFile($mediaId, [
                'private' => $file->isPrivate(),
                'path' => $file->hasPath() ? $file->getPath() : null,
                'extension' => $file->getFileExtension(),
                'size' => $file->getFileSize(),
            ], $allowedExtensions, $errorPath, $violations);
        }
    }

    /**
     * @param array<string, OutcomeRow> $writtenFilesById
     */
    private function validateChangedLinkedFiles(PreWriteValidationEvent $event, array $writtenFilesById, ConstraintViolationList $violations): void
    {
        $changedFiles = array_filter($writtenFilesById, static function (OutcomeRow $file): bool {
            if ($file->isNew) {
                return false;
            }

            foreach (self::FILE_COLUMNS as $column) {
                if (self::normalize($file->getResultingValue($column)) !== self::normalize($file->getStoredValue($column))) {
                    return true;
                }
            }

            return false;
        });

        if ($changedFiles === []) {
            return;
        }

        $changedFileIds = array_keys($changedFiles);
        $criteria = new Criteria($changedFileIds);
        $criteria->setTitle('product-document-validator::linked-files');
        $criteria->addFilter(new EqualsAnyFilter('productDocumentTranslations.mediaId', $changedFileIds));

        $linkedMediaIds = $event->getContext()->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $context): array => $this->mediaRepository->searchIds($criteria, $context)->getIds(),
        );

        if ($linkedMediaIds === []) {
            return;
        }

        $allowedExtensions = $this->config->getAllowedExtensions($event->getContext());
        foreach ($linkedMediaIds as $mediaId) {
            \assert(\is_string($mediaId));
            $this->validateFile($mediaId, $this->writtenFile($changedFiles[$mediaId]), $allowedExtensions, $changedFiles[$mediaId]->path, $violations);
        }
    }

    /**
     * @param array{private: bool, path: ?string, extension: ?string, size: ?int} $file
     * @param list<string> $allowedExtensions
     */
    private function validateFile(string $mediaId, array $file, array $allowedExtensions, string $errorPath, ConstraintViolationList $violations): void
    {
        if ($file['private']) {
            $violations->add($this->violation(
                code: self::VIOLATION_FILE_PRIVATE,
                messageTemplate: 'The file "{{ mediaId }}" must be public.',
                parameters: ['{{ mediaId }}' => $mediaId],
                path: $errorPath,
            ));

            return;
        }

        // a linked file lives on another server and can change at any time
        if ($file['path'] !== null && MediaUploadService::isExternalUrl($file['path'])) {
            $violations->add($this->violation(
                code: self::VIOLATION_FILE_SIZE_UNKNOWN,
                messageTemplate: 'The file "{{ mediaId }}" must not be a link to another server.',
                parameters: ['{{ mediaId }}' => $mediaId],
                path: $errorPath,
            ));

            return;
        }

        $size = $file['size'];
        if ($size === null) {
            $violations->add($this->violation(
                code: self::VIOLATION_FILE_SIZE_UNKNOWN,
                messageTemplate: 'The size of the file "{{ mediaId }}" is unknown.',
                parameters: ['{{ mediaId }}' => $mediaId],
                path: $errorPath,
            ));

            return;
        }

        $extension = (string) $file['extension'];
        if (!\in_array(mb_strtolower($extension), $allowedExtensions, true)) {
            $violations->add($this->violation(
                code: self::VIOLATION_FILE_EXTENSION_NOT_ALLOWED,
                messageTemplate: 'The file type "{{ extension }}" is not allowed for product documents. Allowed are: {{ allowed }}.',
                parameters: ['{{ extension }}' => $extension, '{{ allowed }}' => implode(', ', $allowedExtensions)],
                path: $errorPath,
            ));
        }

        if ($size > $this->config->maxFileSize) {
            $violations->add($this->violation(
                code: self::VIOLATION_FILE_TOO_LARGE,
                messageTemplate: 'The file is {{ size }}, over the limit of {{ maxSize }}.',
                parameters: ['{{ size }}' => MemorySizeCalculator::formatToBytes($size), '{{ maxSize }}' => MemorySizeCalculator::formatToBytes($this->config->maxFileSize)],
                path: $errorPath,
            ));
        }
    }

    /**
     * @return array{private: bool, path: ?string, extension: ?string, size: ?int}
     */
    private function writtenFile(OutcomeRow $file): array
    {
        $path = $file->getResultingValue('path');
        $extension = $file->getResultingValue('file_extension');
        $size = $file->getResultingValue('file_size');

        return [
            'private' => (bool) $file->getResultingValue('private'),
            'path' => \is_string($path) && $path !== '' ? $path : null,
            'extension' => \is_string($extension) ? $extension : null,
            'size' => is_numeric($size) ? (int) $size : null,
        ];
    }

    private static function normalize(mixed $value): ?string
    {
        return \is_scalar($value) ? (string) $value : null;
    }

    private function defaultLanguageFileMissing(string $path): ConstraintViolation
    {
        return $this->violation(
            code: self::VIOLATION_DEFAULT_LANGUAGE_FILE_MISSING,
            messageTemplate: 'The document needs a file in the default language.',
            parameters: [],
            path: $path,
        );
    }

    /**
     * @param list<string> $mediaIds
     */
    private function loadFiles(array $mediaIds, Context $context): MediaCollection
    {
        $criteria = new Criteria(array_unique($mediaIds));
        $criteria->setTitle('product-document-validator::files');

        return $this->mediaRepository->search($criteria, $context)->getEntities();
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
