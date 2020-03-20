<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

final class FrwState
{
    /**
     * @var \DateTimeImmutable|null
     */
    private $completedAt;

    /**
     * @var \DateTimeImmutable|null
     */
    private $failedAt;

    /**
     * @var int
     */
    private $failureCount;

    private function __construct(?\DateTimeImmutable $completedAt = null, ?\DateTimeImmutable $failedAt = null, int $failureCount = 0)
    {
        $this->completedAt = $completedAt;
        $this->failedAt = $failedAt;
        $this->failureCount = $failureCount;
    }

    public static function openState(): FrwState
    {
        return new FrwState();
    }

    public static function completedState(?\DateTimeImmutable $completedAt = null): FrwState
    {
        return new FrwState($completedAt ?? new \DateTimeImmutable());
    }

    public static function failedState(?\DateTimeImmutable $failedAt = null, int $failureCount = 0): FrwState
    {
        return new FrwState(null, $failedAt ?? new \DateTimeImmutable(), $failureCount);
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function isOpen(): bool
    {
        return !$this->isCompleted() && !$this->isFailed();
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function isFailed(): bool
    {
        return $this->failedAt !== null && !$this->isCompleted();
    }

    public function getFailureCount(): int
    {
        if ($this->isFailed()) {
            return $this->failureCount;
        }

        return 0;
    }

    public function getApiAlias(): string
    {
        return 'store_frw_state';
    }
}
