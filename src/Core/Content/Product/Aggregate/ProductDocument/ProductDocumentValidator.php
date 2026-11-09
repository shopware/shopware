<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
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
 * Checks product documents against `shopware.product_documents` before they are written.
 * Only what the write changes is checked: new files, added documents and the default-language file of new documents.
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

    public const VIOLATION_DEFAULT_TRANSLATION_MISSING = 'PRODUCT_DOCUMENT_DEFAULT_TRANSLATION_MISSING';

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly WriteOutcome $writeOutcome,
        private readonly EntityRepository $mediaRepository,
        private readonly ProductDocumentReader $reader,
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
        $documentCommands = $event->getCommandsForEntity(ProductDocumentDefinition::ENTITY_NAME);
        // the product of a document is immutable, so only inserts add documents to a product
        $newDocuments = array_values(array_filter($documentCommands, static fn (WriteCommand $command): bool => $command instanceof InsertCommand));
        $translations = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME),
            ['media_id'],
        );

        if ($newDocuments === [] && $translations === []) {
            return;
        }

        $violations = new ConstraintViolationList();
        $this->validateFiles($event, $translations, $violations);
        $this->validateDefaultTranslations($newDocuments, $translations, $violations);
        $this->validateNumberOfDocuments($newDocuments, $documentCommands, $violations);

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param list<OutcomeRow> $translations
     */
    private function validateFiles(PreWriteValidationEvent $event, array $translations, ConstraintViolationList $violations): void
    {
        $translationsWithNewFile = [];
        foreach ($translations as $translation) {
            $resultingMediaId = $translation->getResultingValue('media_id');
            if (\is_string($resultingMediaId) && $resultingMediaId !== $translation->getStoredValue('media_id')) {
                $translationsWithNewFile[] = [$translation, Uuid::fromBytesToHex($resultingMediaId)];
            }
        }

        if ($translationsWithNewFile === []) {
            return;
        }

        $files = $this->loadFiles(array_values(array_unique(array_column($translationsWithNewFile, 1))), $event->getContext());
        $allowedExtensions = $this->config->getAllowedExtensions($event->getContext());

        foreach ($translationsWithNewFile as [$translation, $mediaId]) {
            $file = $files->get($mediaId);
            if (!$file instanceof MediaEntity) {
                // the database rejects a missing file, and a file the same write creates isn't stored yet
                continue;
            }

            $errorPath = $translation->path . '/mediaId';

            if ($file->isPrivate()) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_PRIVATE,
                    messageTemplate: 'The file "{{ mediaId }}" is private, but shoppers have to be able to download the files of product documents.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));
            }

            // a linked file lives on another server and can change at any time
            if ($file->hasPath() && MediaUploadService::isExternalUrl($file->getPath())) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_SIZE_UNKNOWN,
                    messageTemplate: 'The file "{{ mediaId }}" is a link to another server. Upload it to the shop first.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));

                continue;
            }

            $size = $file->getFileSize();
            if ($size === null) {
                $violations->add($this->violation(
                    code: self::VIOLATION_FILE_SIZE_UNKNOWN,
                    messageTemplate: 'The size of the file "{{ mediaId }}" is unknown. Upload the file first.',
                    parameters: ['{{ mediaId }}' => $mediaId],
                    path: $errorPath,
                ));

                continue;
            }

            $extension = (string) $file->getFileExtension();
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
                    messageTemplate: 'The file is {{ size }}, but the files of product documents can have at most {{ maxSize }}.',
                    parameters: ['{{ size }}' => MemorySizeCalculator::formatToBytes($size), '{{ maxSize }}' => MemorySizeCalculator::formatToBytes($this->config->maxFileSize)],
                    path: $errorPath,
                ));
            }
        }
    }

    /**
     * Only stored files: the Administration uploads a file before the document is saved.
     *
     * @param list<string> $mediaIds hex
     */
    private function loadFiles(array $mediaIds, Context $context): MediaCollection
    {
        $criteria = new Criteria($mediaIds);
        $criteria->setTitle('product-document-validator::files');

        // the system scope keeps private files visible, which the private check needs
        return $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): MediaCollection => $this->mediaRepository->search($criteria, $context)->getEntities());
    }

    /**
     * A new document needs a default-language translation, which other languages fall back to.
     * The DAL doesn't require one, because foreign keys don't count as required translated fields,
     * and core's TranslationValidator keeps it from being deleted later.
     *
     * @param list<WriteCommand> $newDocuments
     * @param list<OutcomeRow> $translations
     */
    private function validateDefaultTranslations(array $newDocuments, array $translations, ConstraintViolationList $violations): void
    {
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $documentsWithDefaultTranslation = [];
        foreach ($translations as $translation) {
            if ($translation->primaryKey['language_id'] === $systemLanguageId) {
                $documentsWithDefaultTranslation[$translation->primaryKey['product_document_id'] . $translation->primaryKey['product_document_version_id']] = true;
            }
        }

        foreach ($newDocuments as $document) {
            $primaryKey = $document->getPrimaryKey();
            if (!isset($documentsWithDefaultTranslation[$primaryKey['id'] . $primaryKey['version_id']])) {
                $violations->add($this->violation(
                    code: self::VIOLATION_DEFAULT_TRANSLATION_MISSING,
                    messageTemplate: 'The product document "{{ documentId }}" has no file in the default language, which other languages fall back to.',
                    parameters: ['{{ documentId }}' => Uuid::fromBytesToHex($primaryKey['id'])],
                    path: $document->getPath(),
                ));
            }
        }
    }

    /**
     * @param list<WriteCommand> $newDocuments
     * @param list<WriteCommand> $documentCommands
     */
    private function validateNumberOfDocuments(array $newDocuments, array $documentCommands, ConstraintViolationList $violations): void
    {
        $addedPerProduct = [];
        foreach ($newDocuments as $document) {
            $product = $this->productKey($document->getPayload());
            if ($product !== null) {
                $addedPerProduct[$product][] = $document->getPath();
            }
        }

        if ($addedPerProduct === []) {
            return;
        }

        // documents the same write deletes make room, for example when a file is replaced by a new document
        $deletedDocumentIds = [];
        foreach ($documentCommands as $command) {
            if ($command instanceof DeleteCommand) {
                $deletedDocumentIds[] = $command->getPrimaryKey()['id'];
            }
        }

        $storedCounts = $this->reader->countDocuments(array_keys($addedPerProduct), $deletedDocumentIds);

        foreach ($addedPerProduct as $product => $paths) {
            $overLimit = ($storedCounts[$product] ?? 0) + \count($paths) - $this->config->maxPerProduct;
            if ($overLimit <= 0) {
                continue;
            }

            // the last added documents are the ones over the limit
            foreach (\array_slice($paths, -$overLimit) as $path) {
                $violations->add($this->violation(
                    code: self::VIOLATION_TOO_MANY_DOCUMENTS,
                    messageTemplate: 'A product can have at most {{ max }} documents.',
                    parameters: ['{{ max }}' => (string) $this->config->maxPerProduct],
                    path: $path,
                ));
            }
        }
    }

    /**
     * The product key format of ProductDocumentReader: id and version id in hex.
     *
     * @param array<string, mixed> $payload
     */
    private function productKey(array $payload): ?string
    {
        $productId = $payload['product_id'] ?? null;
        $versionId = $payload['product_version_id'] ?? null;
        if (!\is_string($productId) || !\is_string($versionId)) {
            return null;
        }

        return Uuid::fromBytesToHex($productId) . Uuid::fromBytesToHex($versionId);
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
