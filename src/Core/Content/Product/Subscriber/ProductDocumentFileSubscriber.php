<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentFileStorage;
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
    public function __construct(private readonly ProductDocumentFileStorage $storage)
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
                    $defaultFiles[$documentId] = Uuid::fromBytesToHex($fileId);
                }
            } elseif ($fileId === null && ($command instanceof InsertCommand || $command->hasField('media_id'))) {
                $versionsWithoutFile[$documentId][] = $command;
            }
        }

        $documentIds = array_keys(array_diff_key($versionsWithoutFile, $defaultFiles));
        if ($documentIds !== []) {
            $defaultFiles += $this->storage->getDefaultFiles($documentIds, $event->getContext()->getVersionId());
        }

        foreach ($versionsWithoutFile as $documentId => $commands) {
            if (!isset($defaultFiles[$documentId])) {
                continue;
            }

            foreach ($commands as $command) {
                $command->addPayload('media_id', Uuid::fromHexToBytes($defaultFiles[$documentId]));
            }
        }
    }
}
