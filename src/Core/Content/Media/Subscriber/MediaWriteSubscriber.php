<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
class MediaWriteSubscriber
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function beforeWrite(EntityWriteEvent $event): void
    {
        $context = $event->getContext();
        if ($context->hasState(EntityIndexerRegistry::DISABLE_INDEXING)) {
            return;
        }

        /** @var list<string> $mediaIds */
        $mediaIds = $event->getIds(MediaDefinition::ENTITY_NAME);
        $existingThumbnails = [];
        foreach ($event->getCommandsForEntity(MediaThumbnailDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof JsonUpdateCommand && isset($command->getPayload()['media_id'])) {
                $mediaIds[] = Uuid::fromBytesToHex($command->getPayload()['media_id']);
            }

            if (!$command instanceof InsertCommand) {
                $existingThumbnails[] = $command->getPrimaryKey()['id'];
            }
        }

        if ($existingThumbnails !== []) {
            // Capture the old owner before a thumbnail is deleted or moved to another media entity.
            $previousOwners = $this->connection->fetchFirstColumn(
                'SELECT DISTINCT LOWER(HEX(media_id)) FROM media_thumbnail WHERE id IN (:ids)',
                ['ids' => $existingThumbnails],
                ['ids' => ArrayParameterType::BINARY],
            );
            $mediaIds = array_merge($mediaIds, $previousOwners);
        }

        if ($mediaIds !== []) {
            $skip = $context->getExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP);
            $notification = new MediaIndexerEvent(array_values(array_unique($mediaIds)), $context, $skip instanceof ArrayEntity ? $skip->get('skips') : []);
            $event->addSuccess(function () use ($context, $notification): void {
                if ($context->hasState(EntityIndexerRegistry::USE_INDEXING_QUEUE)) {
                    $this->bus->dispatch($notification);

                    return;
                }

                $this->notify($notification);
            });
        }
    }

    public function notify(MediaIndexerEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
