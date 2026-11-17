<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A new or replaced file saved without a title names the document after the file, without the extension, in the default language.
 * Translations in other languages keep their empty title and fall back to that one.
 *
 * @internal
 */
#[Package('inventory')]
final class ProductDocumentTitleSubscriber implements EventSubscriberInterface
{
    /**
     * The length of the title column.
     */
    private const MAX_TITLE_LENGTH = 255;

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'setTitleFromFileName',
        ];
    }

    public function setTitleFromFileName(EntityWriteEvent $event): void
    {
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $commands = [];
        $mediaIds = [];
        $updatedDocumentIds = [];
        foreach ($event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME) as $command) {
            $primaryKey = $command->getPrimaryKey();
            $payload = $command->getPayload();
            $mediaId = $payload['media_id'] ?? null;

            if ($primaryKey['language_id'] !== $systemLanguageId
                || ($payload['title'] ?? null) !== null
                || !\is_string($mediaId)
            ) {
                continue;
            }

            $translationKey = self::createTranslationKey($primaryKey);
            $commands[$translationKey] = $command;
            $mediaIds[$translationKey] = $mediaId;

            if ($command instanceof UpdateCommand) {
                $updatedDocumentIds[] = $primaryKey['product_document_id'];
            }
        }

        foreach ($this->fetchStoredMediaIds($updatedDocumentIds) as $translationKey => $storedMediaId) {
            if (($mediaIds[$translationKey] ?? null) === $storedMediaId) {
                unset($mediaIds[$translationKey]);
            }
        }

        if ($mediaIds === []) {
            return;
        }

        $fileNames = $this->connection->fetchAllKeyValue(
            'SELECT `id`, `file_name` FROM `media` WHERE `id` IN (:ids) AND `file_name` IS NOT NULL AND `file_name` != \'\'',
            ['ids' => array_values(array_unique($mediaIds))],
            ['ids' => ArrayParameterType::BINARY],
        );

        foreach ($mediaIds as $translationKey => $mediaId) {
            $fileName = $fileNames[$mediaId] ?? null;
            if (\is_string($fileName)) {
                $commands[$translationKey]->addPayload('title', mb_substr($fileName, 0, self::MAX_TITLE_LENGTH));
            }
        }
    }

    /**
     * @param list<string> $documentIds
     *
     * @return array<string, mixed>
     */
    private function fetchStoredMediaIds(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        $storedTranslations = $this->connection->fetchAllAssociative(
            'SELECT `product_document_id`, `product_document_version_id`, `language_id`, `media_id` FROM `product_document_translation` WHERE `product_document_id` IN (:ids)',
            ['ids' => array_values(array_unique($documentIds))],
            ['ids' => ArrayParameterType::BINARY],
        );

        $storedMediaIds = [];
        foreach ($storedTranslations as $translation) {
            $storedMediaIds[self::createTranslationKey($translation)] = $translation['media_id'];
        }

        return $storedMediaIds;
    }

    /**
     * @param array<string, mixed> $primaryKey
     */
    private static function createTranslationKey(array $primaryKey): string
    {
        return $primaryKey['product_document_id'] . $primaryKey['product_document_version_id'] . $primaryKey['language_id'];
    }
}
