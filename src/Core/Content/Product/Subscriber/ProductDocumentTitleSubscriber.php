<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A document created without a title gets the name of its file, without the extension, as title in the default language.
 * Versions in other languages keep their empty title, so they fall back to that one.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\Subscriber\ProductDocumentTitleSubscriberTest
 */
#[Package('inventory')]
class ProductDocumentTitleSubscriber implements EventSubscriberInterface
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

        // new default versions without a title, with the id of their file
        $versions = [];
        foreach ($event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME) as $command) {
            $payload = $command->getPayload();
            $mediaId = $payload['media_id'] ?? null;

            if ($command instanceof InsertCommand
                && $command->getPrimaryKey()['language_id'] === $systemLanguageId
                && ($payload['title'] ?? null) === null
                && \is_string($mediaId)
            ) {
                $versions[] = [$command, Uuid::fromBytesToHex($mediaId)];
            }
        }

        if ($versions === []) {
            return;
        }

        $fileNames = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `file_name` FROM `media` WHERE `id` IN (:ids) AND `file_name` IS NOT NULL AND `file_name` != \'\'',
            ['ids' => Uuid::fromHexToBytesList(array_values(array_unique(array_column($versions, 1))))],
            ['ids' => ArrayParameterType::BINARY],
        );

        foreach ($versions as [$command, $mediaId]) {
            $fileName = $fileNames[$mediaId] ?? null;
            if (\is_string($fileName)) {
                $command->addPayload('title', mb_substr($fileName, 0, self::MAX_TITLE_LENGTH));
            }
        }
    }
}
