<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Run;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportV2RunEntity extends Entity
{
    use EntityIdTrait;

    public const STATE_QUEUED = 'queued';

    public const STATE_RUNNING = 'running';

    public const STATE_CANCEL_REQUESTED = 'cancel_requested';

    public const STATE_CANCELED = 'canceled';

    public const STATE_COMPLETED = 'completed';

    public const STATE_FAILED = 'failed';

    /**
     * Type can be import or export
     */
    protected string $type;

    /**
     * The profile's technical name
     */
    protected string $profileName;

    /**
     * See STATE_* constants
     */
    protected string $state = self::STATE_QUEUED;

    /**
     * Number of records already processed across finished chunks.
     *
     * Example:
     * - after two chunks of 100 records each: `200`
     */
    protected int $processed = 0;

    /**
     * Number of records exported successfully.
     *
     * Example:
     * - if 97 rows were written successfully: `97`
     */
    protected int $succeeded = 0;

    /**
     * Number of records that failed during processing.
     *
     * Example:
     * - if one chunk fails: `1`
     */
    protected int $failed = 0;

    /**
     * Zero-based position of the next record batch.
     *
     * Example:
     * - `0` for the first chunk
     * - `200` after exporting two 100-record chunks
     */
    protected int $offset = 0;

    /**
     * Maximum number of records to load in one chunk.
     *
     * Example:
     * - `100`
     */
    protected int $limit = 100;

    /**
     * Physical byte offset of the next unread import record in the source
     * file. Export does not need this, but streamed imports can resume from
     * here instead of rescanning from byte 0 every chunk.
     *
     * Example:
     * - `12345` means "continue reading the next chunk from byte 12,345"
     */
    protected ?int $nextByteOffset = null;

    /**
     * Total number of records that match the export query.
     *
     * Example:
     * - if the export matches 2,500 products: `2500`
     */
    protected ?int $totalRecords = null;

    /**
     * Stored DAL filter payload used to rebuild the same export query later.
     *
     * Example:
     * ```php
     * [
     *     ['type' => 'equals', 'field' => 'active', 'value' => true],
     * ]
     * ```
     *
     * @var list<array<string, mixed>>
     */
    protected array $exportFilters = [];

    /**
     * Linked export file id.
     */
    protected string $fileId;

    /**
     * Linked invalid-records file id created during import when one or more
     * records fail validation or DAL write.
     */
    protected ?string $invalidRecordsFileId = null;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getProfileName(): string
    {
        return $this->profileName;
    }

    public function setProfileName(string $profileName): void
    {
        $this->profileName = $profileName;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function markQueued(): void
    {
        $this->state = self::STATE_QUEUED;
    }

    public function markRunning(): void
    {
        $this->state = self::STATE_RUNNING;
    }

    public function markCancelRequested(): void
    {
        $this->state = self::STATE_CANCEL_REQUESTED;
    }

    public function markCanceled(): void
    {
        $this->state = self::STATE_CANCELED;
    }

    public function markCompleted(): void
    {
        $this->state = self::STATE_COMPLETED;
    }

    public function markFailed(): void
    {
        $this->state = self::STATE_FAILED;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function addProcessed(int $count): void
    {
        $this->processed += $count;
    }

    public function getSucceeded(): int
    {
        return $this->succeeded;
    }

    public function addSucceeded(int $count): void
    {
        $this->succeeded += $count;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function addFailed(int $count = 1): void
    {
        $this->failed += $count;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = max(0, $offset);
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = max(1, $limit);
    }

    public function getNextByteOffset(): ?int
    {
        return $this->nextByteOffset;
    }

    public function setNextByteOffset(?int $nextByteOffset): void
    {
        $this->nextByteOffset = $nextByteOffset !== null ? max(0, $nextByteOffset) : null;
    }

    public function getTotalRecords(): ?int
    {
        return $this->totalRecords;
    }

    public function setTotalRecords(?int $totalRecords): void
    {
        $this->totalRecords = $totalRecords;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getExportFilters(): array
    {
        return $this->exportFilters;
    }

    /**
     * @param list<array<string, mixed>> $exportFilters
     */
    public function setExportFilters(array $exportFilters): void
    {
        $this->exportFilters = $exportFilters;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function setFileId(string $fileId): void
    {
        $this->fileId = $fileId;
    }

    public function getInvalidRecordsFileId(): ?string
    {
        return $this->invalidRecordsFileId;
    }

    public function setInvalidRecordsFileId(?string $invalidRecordsFileId): void
    {
        $this->invalidRecordsFileId = $invalidRecordsFileId;
    }
}
