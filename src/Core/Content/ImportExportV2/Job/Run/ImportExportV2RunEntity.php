<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Run;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportV2RunEntity extends Entity
{
    use EntityIdTrait;

    final public const STATE_QUEUED = 'queued';

    final public const STATE_RUNNING = 'running';

    final public const STATE_COMPLETED = 'completed';

    final public const STATE_FAILED = 'failed';

    final public const STATE_CANCEL_REQUESTED = 'cancel_requested';

    final public const STATE_CANCELED = 'canceled';

    private const DEFAULT_CHUNK_SIZE = 100;

    protected string $type;

    protected string $profileName;

    protected string $state = self::STATE_QUEUED;

    protected int $processed = 0;

    protected int $succeeded = 0;

    protected int $failed = 0;

    /**
     * @var list<array{recordIndex:int,message:string}>
     */
    protected array $failures = [];

    /**
     * The cursor stores the worker progress that lets a queued run continue
     * with the next chunk instead of restarting from the beginning.
     *
     * @var array{offset?:int,chunkSize?:int}
     */
    protected array $cursor = [];

    protected ?int $totalRecords = null;

    protected ?string $lastError = null;

    protected ?string $processingToken = null;

    protected ?\DateTimeInterface $processingExpiresAt = null;

    protected ?string $inputArtifactId = null;

    protected ?string $outputArtifactId = null;

    /**
     * @var list<string>
     */
    protected array $recordIds = [];

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

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function markQueued(): void
    {
        $this->state = self::STATE_QUEUED;
    }

    public function markRunning(): void
    {
        $this->state = self::STATE_RUNNING;
    }

    public function markCompleted(): void
    {
        $this->state = self::STATE_COMPLETED;
    }

    public function markFailed(): void
    {
        $this->state = self::STATE_FAILED;
    }

    public function markCancelRequested(): void
    {
        $this->state = self::STATE_CANCEL_REQUESTED;
    }

    public function markCanceled(): void
    {
        $this->state = self::STATE_CANCELED;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function setProcessed(int $processed): void
    {
        $this->processed = $processed;
    }

    public function addProcessed(int $count = 1): void
    {
        $this->processed += $count;
    }

    public function getSucceeded(): int
    {
        return $this->succeeded;
    }

    public function setSucceeded(int $succeeded): void
    {
        $this->succeeded = $succeeded;
    }

    public function addSucceeded(int $count): void
    {
        $this->succeeded += $count;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function setFailed(int $failed): void
    {
        $this->failed = $failed;
    }

    public function addFailure(int $recordIndex, string $message): void
    {
        $this->failures[] = [
            'recordIndex' => $recordIndex,
            'message' => $message,
        ];
        ++$this->failed;
    }

    /**
     * @return list<array{recordIndex:int,message:string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @param list<array{recordIndex:int,message:string}> $failures
     */
    public function setFailures(array $failures): void
    {
        $this->failures = $failures;
    }

    /**
     * @return array{offset?:int,chunkSize?:int}
     */
    public function getCursor(): array
    {
        return $this->cursor;
    }

    /**
     * @param array{offset?:int,chunkSize?:int} $cursor
     */
    public function setCursor(array $cursor): void
    {
        $this->cursor = $cursor;
    }

    public function getOffset(): int
    {
        return (int) ($this->cursor['offset'] ?? 0);
    }

    public function setOffset(int $offset): void
    {
        $this->cursor['offset'] = max(0, $offset);
    }

    public function getChunkSize(): int
    {
        return max(1, (int) ($this->cursor['chunkSize'] ?? self::DEFAULT_CHUNK_SIZE));
    }

    public function setChunkSize(int $chunkSize): void
    {
        $this->cursor['chunkSize'] = max(1, $chunkSize);
    }

    public function getTotalRecords(): ?int
    {
        return $this->totalRecords;
    }

    public function setTotalRecords(?int $totalRecords): void
    {
        $this->totalRecords = $totalRecords;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): void
    {
        $this->lastError = $lastError;
    }

    public function getProcessingToken(): ?string
    {
        return $this->processingToken;
    }

    public function setProcessingToken(?string $processingToken): void
    {
        $this->processingToken = $processingToken;
    }

    public function getProcessingExpiresAt(): ?\DateTimeInterface
    {
        return $this->processingExpiresAt;
    }

    public function setProcessingExpiresAt(?\DateTimeInterface $processingExpiresAt): void
    {
        $this->processingExpiresAt = $processingExpiresAt;
    }

    public function clearLease(): void
    {
        $this->processingToken = null;
        $this->processingExpiresAt = null;
    }

    public function getInputArtifactId(): ?string
    {
        return $this->inputArtifactId;
    }

    public function setInputArtifactId(?string $inputArtifactId): void
    {
        $this->inputArtifactId = $inputArtifactId;
    }

    public function getOutputArtifactId(): ?string
    {
        return $this->outputArtifactId;
    }

    public function setOutputArtifactId(?string $outputArtifactId): void
    {
        $this->outputArtifactId = $outputArtifactId;
    }

    /**
     * @return list<string>
     */
    public function getRecordIds(): array
    {
        return $this->recordIds;
    }

    /**
     * @param list<string> $recordIds
     */
    public function setRecordIds(array $recordIds): void
    {
        $this->recordIds = $recordIds;
    }
}
