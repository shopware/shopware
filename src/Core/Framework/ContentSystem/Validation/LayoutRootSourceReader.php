<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads the immutable root source of content layouts at write time: a layout the batch writes from its state after
 * the batch (so an atomic create-and-bind sees the inserted value), any other layout from the stored row.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutRootSourceReader
{
    public function __construct(
        private readonly WriteOutcome $writeOutcome,
        private readonly RowLoader $rowLoader,
    ) {
    }

    /**
     * The root source of each layout, keyed by the id as passed. A layout the batch deletes, one that is neither
     * written nor stored, or a hex id not in the lowercase form the DAL stores, is left out.
     *
     * @param list<string> $layoutIds hex ids, or 16 binary bytes as in a foreign-key payload
     * @param list<WriteCommand> $layoutCommands the batch's content_layout commands, empty outside a write
     *
     * @return array<string, string>
     */
    public function rootSources(array $layoutIds, array $layoutCommands): array
    {
        $bytesById = [];
        foreach ($layoutIds as $layoutId) {
            $bytesById[$layoutId] = \strlen($layoutId) === 16 ? $layoutId : Uuid::fromHexToBytes($layoutId);
        }

        $written = $this->writtenRootSources($layoutCommands);
        $stored = $this->storedRootSources(array_values(array_diff(array_unique($bytesById), array_keys($written))));

        $rootSources = [];
        foreach ($bytesById as $layoutId => $bytes) {
            $rootSource = $written[$bytes] ?? $stored[$bytes] ?? null;
            $isStoredForm = \strlen($layoutId) === 16 || $layoutId === Uuid::fromBytesToHex($bytes);

            if ($isStoredForm && \is_string($rootSource)) {
                $rootSources[$layoutId] = $rootSource;
            }
        }

        return $rootSources;
    }

    /**
     * @param list<WriteCommand> $layoutCommands
     *
     * @return array<string, mixed> keyed by binary id, null for a layout the batch deletes
     */
    private function writtenRootSources(array $layoutCommands): array
    {
        $rootSources = [];
        foreach ($this->writeOutcome->touchedRows($layoutCommands, [ContentLayoutDefinition::ROOT_SOURCE_FIELD]) as $row) {
            $rootSources[$row->primaryKey['id']] = $row->getResultingValue(ContentLayoutDefinition::ROOT_SOURCE_FIELD);
        }

        return $rootSources;
    }

    /**
     * @param list<string> $layoutIds binary ids
     *
     * @return array<string, mixed> keyed by binary id
     */
    private function storedRootSources(array $layoutIds): array
    {
        $rows = $this->rowLoader->load(
            ContentLayoutDefinition::ENTITY_NAME,
            array_map(static fn (string $id): array => ['id' => $id], $layoutIds),
            ['id', ContentLayoutDefinition::ROOT_SOURCE_FIELD],
        );

        return array_column($rows, ContentLayoutDefinition::ROOT_SOURCE_FIELD, 'id');
    }
}
