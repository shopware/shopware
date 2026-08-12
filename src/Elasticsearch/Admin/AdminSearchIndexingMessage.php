<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\DeduplicatableMessageInterface;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @internal
 */
#[Package('inventory')]
final readonly class AdminSearchIndexingMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @param array<string, string> $indices
     * @param list<string> $ids
     * @param list<string> $toRemoveIds
     */
    public function __construct(
        private string $entity,
        private string $indexer,
        private array $indices,
        private array $ids,
        private array $toRemoveIds = [],
        private bool $indexingRun = false
    ) {
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    /**
     * @return array<string, string>
     */
    public function getIndices(): array
    {
        return $this->indices;
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * Whether this message belongs to a full indexing run. Only those count down the remaining document count of
     * the {@see \Shopware\Elasticsearch\Admin\AdminCreateAliasTask} that promotes the index; live updates of single
     * entities must not.
     */
    public function isIndexingRun(): bool
    {
        return $this->indexingRun;
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:DEDUPLICATABLE_MESSAGES
     */
    public function deduplicationId(): ?string
    {
        $sortedIds = $this->ids;
        sort($sortedIds);

        $sortedIndices = $this->indices;
        ksort($sortedIndices);

        $data = json_encode([
            $this->entity,
            $this->indexer,
            $sortedIndices,
            $sortedIds,
            $this->indexingRun,
        ]);

        if ($data === false) {
            return null;
        }

        return Hasher::hash($data);
    }

    /**
     * @return list<string>
     */
    public function getToRemoveIds(): array
    {
        return $this->toRemoveIds;
    }
}
