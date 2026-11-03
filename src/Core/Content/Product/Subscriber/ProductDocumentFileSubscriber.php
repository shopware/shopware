<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentFileStorage;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gives every language version of a product document a file.
 * A version written without a file gets the file of the default language.
 * When the default language gets another file, the versions that still have the old file get the new one.
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
        $commandsPerDocument = [];
        foreach ($event->getCommandsForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME) as $command) {
            $primaryKey = $command->getPrimaryKey();
            $documentId = Uuid::fromBytesToHex($primaryKey['product_document_id']);
            $commandsPerDocument[$documentId][Uuid::fromBytesToHex($primaryKey['product_document_version_id'])][] = $command;
        }

        if ($commandsPerDocument === []) {
            return;
        }

        $versionIds = array_values(array_unique(array_merge(...array_map(array_keys(...), array_values($commandsPerDocument)))));
        $storedFiles = $this->storage->getFiles(array_keys($commandsPerDocument), $versionIds);

        $moves = [];
        foreach ($commandsPerDocument as $documentId => $commandsPerVersion) {
            foreach ($commandsPerVersion as $versionId => $commands) {
                $documentMoves = $this->updateFiles($storedFiles[$documentId][$versionId] ?? [], $commands);
                foreach ($documentMoves as $languageId => [$oldFileId, $newFileId]) {
                    $moves[] = ['documentId' => $documentId, 'versionId' => $versionId, 'languageId' => $languageId, 'oldFileId' => $oldFileId, 'newFileId' => $newFileId];
                }
            }
        }

        if ($moves !== []) {
            $event->addSuccess(fn () => $this->storage->moveFiles($moves));
        }
    }

    /**
     * Fills the files the write leaves empty, and gives a new default file to the versions that still have the old one.
     *
     * @param array<string, string|null> $storedFiles file id per language id, before the write
     * @param non-empty-list<WriteCommand> $commands the writes of one document version, in the order they run
     *
     * @return array<string, array{string, string}> old and new file id per language id, for versions outside the write
     */
    private function updateFiles(array $storedFiles, array $commands): array
    {
        $files = $storedFiles;
        $writtenVersions = [];
        $ownFiles = [];
        foreach ($commands as $command) {
            $languageId = Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);

            if ($command instanceof DeleteCommand) {
                unset($files[$languageId], $writtenVersions[$languageId], $ownFiles[$languageId]);

                continue;
            }

            $writtenVersions[$languageId] = $command;
            $files += [$languageId => null];
            if ($command instanceof InsertCommand || $command->hasField('media_id')) {
                $fileId = $command->getPayload()['media_id'] ?? null;
                $files[$languageId] = \is_string($fileId) ? Uuid::fromBytesToHex($fileId) : null;
                $ownFiles[$languageId] = $files[$languageId] !== null;
            }
        }

        $defaultFileId = $files[Defaults::LANGUAGE_SYSTEM] ?? null;
        if ($defaultFileId === null) {
            return [];
        }

        foreach ($writtenVersions as $languageId => $command) {
            if ($files[$languageId] === null) {
                $command->addPayload('media_id', Uuid::fromHexToBytes($defaultFileId));
            }
        }

        $oldDefaultFileId = $storedFiles[Defaults::LANGUAGE_SYSTEM] ?? null;
        if ($oldDefaultFileId === null || $oldDefaultFileId === $defaultFileId) {
            return [];
        }

        $moves = [];
        foreach ($files as $languageId => $fileId) {
            if ($fileId !== $oldDefaultFileId || ($ownFiles[$languageId] ?? false)) {
                continue;
            }

            if (isset($writtenVersions[$languageId])) {
                $writtenVersions[$languageId]->addPayload('media_id', Uuid::fromHexToBytes($defaultFileId));
            } else {
                $moves[$languageId] = [$oldDefaultFileId, $defaultFileId];
            }
        }

        return $moves;
    }
}
