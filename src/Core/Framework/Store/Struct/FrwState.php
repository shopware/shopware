<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
final class FrwState
{
    private function __construct(
        private readonly ?\DateTimeImmutable $completedAt = null,
        private readonly ?\DateTimeImmutable $failedAt = null,
        private readonly int $failureCount = 0
    ) {
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
