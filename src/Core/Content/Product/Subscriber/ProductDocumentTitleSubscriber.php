<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
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

        $untitledTranslations = [];
        $updatedTranslations = [];
        foreach ($event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME) as $command) {
            $payload = $command->getPayload();
            $mediaId = $payload['media_id'] ?? null;

            if ($command->getPrimaryKey()['language_id'] !== $systemLanguageId
                || ($payload['title'] ?? null) !== null
                || !\is_string($mediaId)
            ) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                $untitledTranslations[] = [$command, $mediaId];
            } elseif ($command instanceof UpdateCommand) {
                $updatedTranslations[] = [$command, $mediaId];
            }
        }

        $untitledTranslations = [...$untitledTranslations, ...$this->filterReplacedFiles($updatedTranslations)];
        if ($untitledTranslations === []) {
            return;
        }

        $fileNames = $this->connection->fetchAllKeyValue(
            'SELECT `id`, `file_name` FROM `media` WHERE `id` IN (:ids) AND `file_name` IS NOT NULL AND `file_name` != \'\'',
            ['ids' => array_values(array_unique(array_column($untitledTranslations, 1)))],
            ['ids' => ArrayParameterType::BINARY],
        );

        foreach ($untitledTranslations as [$command, $mediaId]) {
            $fileName = $fileNames[$mediaId] ?? null;
            if (\is_string($fileName)) {
                $command->addPayload('title', mb_substr($fileName, 0, self::MAX_TITLE_LENGTH));
            }
        }
    }

    /**
     * @param list<array{UpdateCommand, string}> $translations
     *
     * @return list<array{UpdateCommand, string}>
     */
    private function filterReplacedFiles(array $translations): array
    {
        if ($translations === []) {
            return [];
        }

        $storedTranslations = $this->connection->fetchAllAssociative(
            'SELECT `product_document_id`, `product_document_version_id`, `language_id`, `media_id` FROM `product_document_translation` WHERE `product_document_id` IN (:ids)',
            ['ids' => array_values(array_unique(array_map(static fn (array $translation): string => $translation[0]->getPrimaryKey()['product_document_id'], $translations)))],
            ['ids' => ArrayParameterType::BINARY],
        );

        $storedMediaIds = [];
        foreach ($storedTranslations as $storedTranslation) {
            $storedMediaIds[self::createTranslationKey($storedTranslation)] = $storedTranslation['media_id'];
        }

        return array_values(array_filter(
            $translations,
            static fn (array $translation): bool => ($storedMediaIds[self::createTranslationKey($translation[0]->getPrimaryKey())] ?? null) !== $translation[1],
        ));
    }

    /**
     * @param array<string, mixed> $primaryKey
     */
    private static function createTranslationKey(array $primaryKey): string
    {
        return $primaryKey['product_document_id'] . $primaryKey['product_document_version_id'] . $primaryKey['language_id'];
    }
}
