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
 * Gives every language version of a product document a file.
 * A version written without a file gets the file of the default language.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDocumentFileSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [EntityWriteEvent::class => 'beforeWrite'];
    }

    public function beforeWrite(EntityWriteEvent $event): void
    {
        $defaultFiles = [];
        $versionsWithoutFile = [];
        foreach ($event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME) as $command) {
            $primaryKey = $command->getPrimaryKey();
            $documentId = Uuid::fromBytesToHex($primaryKey['product_document_id']);
            $fileId = $command->getPayload()['media_id'] ?? null;

            if (Uuid::fromBytesToHex($primaryKey['language_id']) === Defaults::LANGUAGE_SYSTEM) {
                if (\is_string($fileId)) {
                    $defaultFiles[$documentId] = $fileId;
                }
            } elseif ($fileId === null && ($command instanceof InsertCommand || $command->hasField('media_id'))) {
                // new versions without a file, and versions whose file is removed
                $versionsWithoutFile[$documentId][] = $command;
            }
        }

        $documentIds = array_keys(array_diff_key($versionsWithoutFile, $defaultFiles));
        if ($documentIds !== []) {
            $defaultFiles += $this->getDefaultFiles($documentIds, $event->getContext()->getVersionId());
        }

        foreach ($versionsWithoutFile as $documentId => $commands) {
            if (!isset($defaultFiles[$documentId])) {
                continue;
            }

            foreach ($commands as $command) {
                $command->addPayload('media_id', $defaultFiles[$documentId]);
            }
        }
    }

    /**
     * @param list<string> $documentIds
     *
     * @return array<string, string> file id per document id
     */
    private function getDefaultFiles(array $documentIds, string $versionId): array
    {
        /** @var array<string, string> */
        return $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`product_document_id`)), `media_id`
             FROM `product_document_translation`
             WHERE `product_document_id` IN (:documentIds) AND `product_document_version_id` = :versionId
               AND `language_id` = :languageId AND `media_id` IS NOT NULL',
            [
                'documentIds' => Uuid::fromHexToBytesList($documentIds),
                'versionId' => Uuid::fromHexToBytes($versionId),
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ],
            ['documentIds' => ArrayParameterType::BINARY],
        );
    }
}
