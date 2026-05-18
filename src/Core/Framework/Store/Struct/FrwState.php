<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final readonly class FrwState
{
    private function __construct(
        private ?\DateTimeImmutable $completedAt = null,
        private ?\DateTimeImmutable $failedAt = null,
        private int $failureCount = 0
    ) {
    }

    public static function openState(): FrwState
    {
        return new FrwState();
    }

    public static function completedState(?\DateTimeImmutable $completedAt = null): FrwState
    {
        // @TODO clock-static: NativeClock fallback in static method; consider refactor to accept clock parameter
        return new FrwState($completedAt ?? (new NativeClock())->now());
    }

    public static function failedState(?\DateTimeImmutable $failedAt = null, int $failureCount = 0): FrwState
    {
        // @TODO clock-static: NativeClock fallback in static method; consider refactor to accept clock parameter
        return new FrwState(null, $failedAt ?? (new NativeClock())->now(), $failureCount);
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
