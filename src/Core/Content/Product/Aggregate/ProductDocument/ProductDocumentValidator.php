<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Checks written product documents against the limits in `shopware.product_documents`.
 * It runs after the write and before the commit, so it sees the documents a product already has.
 * Only what a write changes is checked: a file when a version gets it, the number of documents when one is added,
 * and the default version of every written document.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidatorTest
 */
#[Package('inventory')]
class ProductDocumentValidator implements EventSubscriberInterface
{
    final public const VIOLATION_TOO_MANY_DOCUMENTS = 'PRODUCT_DOCUMENT_TOO_MANY_DOCUMENTS';

    final public const VIOLATION_FILE_EXTENSION_NOT_ALLOWED = 'PRODUCT_DOCUMENT_FILE_EXTENSION_NOT_ALLOWED';

    final public const VIOLATION_FILE_TOO_LARGE = 'PRODUCT_DOCUMENT_FILE_TOO_LARGE';

    final public const VIOLATION_FILE_SIZE_UNKNOWN = 'PRODUCT_DOCUMENT_FILE_SIZE_UNKNOWN';

    final public const VIOLATION_FILE_PRIVATE = 'PRODUCT_DOCUMENT_FILE_PRIVATE';

    final public const VIOLATION_DEFAULT_VERSION_MISSING = 'PRODUCT_DOCUMENT_DEFAULT_VERSION_MISSING';

    public function __construct(
        private readonly Connection $connection,
        private readonly ProductDocumentLimits $limits,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PostWriteValidationEvent $event): void
    {
        $documentCommands = $this->getWriteCommands($event, ProductDocumentDefinition::ENTITY_NAME);
        $translationCommands = $this->getWriteCommands($event, ProductDocumentTranslationDefinition::ENTITY_NAME);

        if ($documentCommands === [] && $translationCommands === []) {
            return;
        }

        $violations = new ConstraintViolationList();
        $this->validateFiles($translationCommands, $violations);
        $this->validateDefaultVersions($event, $documentCommands, $translationCommands, $violations);
        $this->validateNumberOfDocuments($documentCommands, $violations);

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @return list<WriteCommand>
     */
    private function getWriteCommands(PostWriteValidationEvent $event, string $entityName): array
    {
        return array_values(array_filter(
            $event->getCommandsForEntity($entityName),
            static fn (WriteCommand $command): bool => $command instanceof InsertCommand || $command instanceof UpdateCommand,
        ));
    }

    /**
     * @param list<WriteCommand> $translationCommands
     */
    private function validateFiles(array $translationCommands, ConstraintViolationList $violations): void
    {
        // versions that get a file with this write, with the file's id
        $versions = [];
        foreach ($translationCommands as $command) {
            $mediaId = $command->getPayload()['media_id'] ?? null;
            if (\is_string($mediaId)) {
                $versions[] = [$command, Uuid::fromBytesToHex($mediaId)];
            }
        }

        if ($versions === []) {
            return;
        }

        /** @var array<string, array{file_extension: string|null, file_size: string|null, private: string}> $files */
        $files = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(`id`)), `file_extension`, `file_size`, `private` FROM `media` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_values(array_unique(array_column($versions, 1))))],
            ['ids' => ArrayParameterType::BINARY],
        );

        foreach ($versions as [$command, $mediaId]) {
            $file = $files[$mediaId] ?? null;
            if ($file === null) {
                continue;
            }

            $path = $command->getPath() . '/mediaId';

            if ((bool) $file['private']) {
                $violations->add($this->violation(
                    self::VIOLATION_FILE_PRIVATE,
                    'The file "{{ mediaId }}" is private, but shoppers have to be able to download the files of product documents.',
                    ['{{ mediaId }}' => $mediaId],
                    $path,
                ));
            }

            // an external file or one that was never uploaded
            if ($file['file_size'] === null) {
                $violations->add($this->violation(
                    self::VIOLATION_FILE_SIZE_UNKNOWN,
                    'The size of the file "{{ mediaId }}" is unknown. Product documents need an uploaded file.',
                    ['{{ mediaId }}' => $mediaId],
                    $path,
                ));

                continue;
            }

            if (!$this->limits->isAllowedExtension($file['file_extension'])) {
                $violations->add($this->violation(
                    self::VIOLATION_FILE_EXTENSION_NOT_ALLOWED,
                    'The file type "{{ extension }}" is not allowed for product documents. Allowed are: {{ allowed }}.',
                    ['{{ extension }}' => (string) $file['file_extension'], '{{ allowed }}' => implode(', ', $this->limits->allowedExtensions)],
                    $path,
                ));
            }

            if ((int) $file['file_size'] > $this->limits->maxFileSize) {
                $violations->add($this->violation(
                    self::VIOLATION_FILE_TOO_LARGE,
                    'The file is {{ size }}, but the files of product documents can have at most {{ maxSize }}.',
                    ['{{ size }}' => MemorySizeCalculator::formatToBytes((int) $file['file_size']), '{{ maxSize }}' => MemorySizeCalculator::formatToBytes($this->limits->maxFileSize)],
                    $path,
                ));
            }
        }
    }

    /**
     * Every document needs a version with a file in the default language, which is what other languages fall back to.
     *
     * @param list<WriteCommand> $documentCommands
     * @param list<WriteCommand> $translationCommands
     */
    private function validateDefaultVersions(PostWriteValidationEvent $event, array $documentCommands, array $translationCommands, ConstraintViolationList $violations): void
    {
        // written documents by id and version id, pointing to the document or else to its first written version
        $documents = [];
        foreach ($documentCommands as $command) {
            $primaryKey = $command->getPrimaryKey();
            $documents[$primaryKey['id'] . $primaryKey['version_id']] ??= [$primaryKey['id'], $primaryKey['version_id'], $command->getPath()];
        }
        foreach ($translationCommands as $command) {
            $primaryKey = $command->getPrimaryKey();
            $documents[$primaryKey['product_document_id'] . $primaryKey['product_document_version_id']] ??= [$primaryKey['product_document_id'], $primaryKey['product_document_version_id'], $command->getPath()];
        }

        // deleting the default version deletes the whole document afterwards, see ProductDocumentCleanupSubscriber
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        foreach ($event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME) as $command) {
            $primaryKey = $command->getPrimaryKey();
            if ($command instanceof DeleteCommand && $primaryKey['language_id'] === $systemLanguageId) {
                unset($documents[$primaryKey['product_document_id'] . $primaryKey['product_document_version_id']]);
            }
        }

        if ($documents === []) {
            return;
        }

        $withoutDefaultVersion = $this->connection->fetchAllAssociative(
            'SELECT `document`.`id`, `document`.`version_id`
            FROM `product_document` `document`
            LEFT JOIN `product_document_translation` `default_version`
                ON `default_version`.`product_document_id` = `document`.`id`
                AND `default_version`.`product_document_version_id` = `document`.`version_id`
                AND `default_version`.`language_id` = :systemLanguageId
            WHERE `document`.`id` IN (:ids)
                AND `document`.`version_id` IN (:versionIds)
                AND `default_version`.`media_id` IS NULL',
            [
                'systemLanguageId' => $systemLanguageId,
                'ids' => array_values(array_unique(array_column($documents, 0))),
                'versionIds' => array_values(array_unique(array_column($documents, 1))),
            ],
            ['ids' => ArrayParameterType::BINARY, 'versionIds' => ArrayParameterType::BINARY],
        );

        foreach ($withoutDefaultVersion as $row) {
            $document = $documents[$row['id'] . $row['version_id']] ?? null;
            if ($document === null) {
                continue;
            }

            $violations->add($this->violation(
                self::VIOLATION_DEFAULT_VERSION_MISSING,
                'The product document "{{ documentId }}" has no file in the default language, which other languages fall back to.',
                ['{{ documentId }}' => Uuid::fromBytesToHex($document[0])],
                $document[2],
            ));
        }
    }

    /**
     * Each document exists in the default language, so the default language always has the most documents of a product.
     *
     * @param list<WriteCommand> $documentCommands
     */
    private function validateNumberOfDocuments(array $documentCommands, ConstraintViolationList $violations): void
    {
        // documents added to a product, in write order: new ones, and ones moved from another product
        $added = [];
        foreach ($documentCommands as $command) {
            $payload = $command->getPayload();
            $productId = $payload['product_id'] ?? null;
            if (!\is_string($productId)) {
                continue;
            }

            // a document always has the version of its product
            $productVersionId = $payload['product_version_id'] ?? $command->getPrimaryKey()['version_id'];
            $added[$productId . (\is_string($productVersionId) ? $productVersionId : '')][] = $command;
        }

        if ($added === []) {
            return;
        }

        $products = $this->connection->fetchAllAssociative(
            'SELECT `product_id`, `product_version_id`, COUNT(*) AS `documents`
            FROM `product_document`
            WHERE `product_id` IN (:productIds) AND `product_version_id` IN (:versionIds)
            GROUP BY `product_id`, `product_version_id`',
            [
                'productIds' => array_values(array_unique(array_map(static fn (string $key): string => substr($key, 0, 16), array_keys($added)))),
                'versionIds' => array_values(array_unique(array_map(static fn (string $key): string => substr($key, 16), array_keys($added)))),
            ],
            ['productIds' => ArrayParameterType::BINARY, 'versionIds' => ArrayParameterType::BINARY],
        );

        foreach ($products as $product) {
            $tooMany = (int) $product['documents'] - $this->limits->maxPerLanguage;
            $commands = $added[$product['product_id'] . $product['product_version_id']] ?? [];
            if ($tooMany <= 0 || $commands === []) {
                continue;
            }

            // the documents added last are the ones that don't fit anymore
            foreach (\array_slice($commands, -$tooMany) as $command) {
                $violations->add($this->violation(
                    self::VIOLATION_TOO_MANY_DOCUMENTS,
                    'A product can have at most {{ max }} documents per language.',
                    ['{{ max }}' => (string) $this->limits->maxPerLanguage],
                    $command->getPath(),
                ));
            }
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function violation(string $code, string $messageTemplate, array $parameters, string $path): ConstraintViolation
    {
        return new ConstraintViolation(
            strtr($messageTemplate, $parameters),
            $messageTemplate,
            $parameters,
            null,
            $path,
            null,
            code: $code,
        );
    }
}
