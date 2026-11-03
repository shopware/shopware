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
 * A version written without a file gets the file of the language it falls back to: its parent language, otherwise the default language.
 * When a version gets another file, the versions that fall back to it and still have the old file get the new one.
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
        $parentLanguages = $this->storage->getParentLanguages();

        $moves = [];
        foreach ($commandsPerDocument as $documentId => $commandsPerVersion) {
            foreach ($commandsPerVersion as $versionId => $commands) {
                $documentMoves = $this->updateFiles($storedFiles[$documentId][$versionId] ?? [], $commands, $parentLanguages);
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
     * Fills the files the write leaves empty, and gives the new file to the versions that follow a changed one.
     *
     * @param array<string, string|null> $storedFiles file id per language id, before the write
     * @param non-empty-list<WriteCommand> $commands the writes of one document version, in the order they run
     * @param array<string, string> $parentLanguages
     *
     * @return array<string, array{string, string}> old and new file id per language id, for versions outside the write
     */
    private function updateFiles(array $storedFiles, array $commands, array $parentLanguages): array
    {
        $files = $storedFiles;
        $touchedLanguageIds = [];
        $writtenVersions = [];
        $ownFiles = [];
        foreach ($commands as $command) {
            $languageId = Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);
            $touchedLanguageIds[$languageId] = true;

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

        foreach ($writtenVersions as $languageId => $command) {
            if ($files[$languageId] !== null || $languageId === Defaults::LANGUAGE_SYSTEM) {
                continue;
            }

            $fileId = $this->resolveFile($files, $parentLanguages, $this->getFallbackLanguage($files, $parentLanguages, $languageId));
            if ($fileId !== null) {
                $files[$languageId] = $fileId;
                $command->addPayload('media_id', Uuid::fromHexToBytes($fileId));
            }
        }

        $changes = [];
        foreach (array_keys($touchedLanguageIds) as $languageId) {
            $changes[] = [$languageId, $this->resolveFile($storedFiles, $parentLanguages, $languageId), $this->resolveFile($files, $parentLanguages, $languageId)];
        }

        $moves = [];
        while ($changes !== []) {
            [$languageId, $oldFileId, $newFileId] = array_shift($changes);
            if ($oldFileId === null || $newFileId === null || $oldFileId === $newFileId) {
                continue;
            }

            foreach ($files as $followerId => $fileId) {
                // before or after the write, for when the write adds or removes the version of a parent language
                $fallbackLanguageIds = [
                    $this->getFallbackLanguage($storedFiles, $parentLanguages, $followerId),
                    $this->getFallbackLanguage($files, $parentLanguages, $followerId),
                ];
                if ($fileId !== $oldFileId || $followerId === $languageId || ($ownFiles[$followerId] ?? false) || !\in_array($languageId, $fallbackLanguageIds, true)) {
                    continue;
                }

                $files[$followerId] = $newFileId;
                if (isset($writtenVersions[$followerId])) {
                    $writtenVersions[$followerId]->addPayload('media_id', Uuid::fromHexToBytes($newFileId));
                } else {
                    $moves[$followerId] = [$oldFileId, $newFileId];
                }
                $changes[] = [$followerId, $oldFileId, $newFileId];
            }
        }

        return $moves;
    }

    /**
     * @param array<string, string|null> $files
     * @param array<string, string> $parentLanguages
     */
    private function resolveFile(array $files, array $parentLanguages, string $languageId): ?string
    {
        if (isset($files[$languageId]) || $languageId === Defaults::LANGUAGE_SYSTEM) {
            return $files[$languageId] ?? null;
        }

        return $this->resolveFile($files, $parentLanguages, $this->getFallbackLanguage($files, $parentLanguages, $languageId));
    }

    /**
     * @param array<string, string|null> $files
     * @param array<string, string> $parentLanguages
     */
    private function getFallbackLanguage(array $files, array $parentLanguages, string $languageId): string
    {
        $parentLanguageId = $parentLanguages[$languageId] ?? null;

        return $parentLanguageId !== null && \array_key_exists($parentLanguageId, $files) ? $parentLanguageId : Defaults::LANGUAGE_SYSTEM;
    }
}
