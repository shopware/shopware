<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class UrlVerificationStatus
{
    private function __construct(public UrlVerificationState $state, public ?\DateTimeImmutable $lastVerifiedAt)
    {
    }

    public function failed(): bool
    {
        return $this->state === UrlVerificationState::FAILED;
    }

    public function passed(): bool
    {
        return $this->state === UrlVerificationState::PASSED;
    }

    public static function newPending(): self
    {
        return new self(UrlVerificationState::PENDING, null);
    }

    public static function newPassed(): self
    {
        return new self(UrlVerificationState::PASSED, new \DateTimeImmutable('now'));
    }

    public static function newFailed(): self
    {
        return new self(UrlVerificationState::FAILED, new \DateTimeImmutable('now'));
    }

    /**
     * @param array{
     *     state: string,
     *     lastVerifiedAt?: string
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $state = UrlVerificationState::from($data['state']);

        $lastVerifiedAt = null;
        if ($state !== UrlVerificationState::PENDING && !empty($data['lastVerifiedAt'])) {
            $lastVerifiedAt = new \DateTimeImmutable($data['lastVerifiedAt']);
        }

        return new self($state, $lastVerifiedAt);
    }

    /**
     * @return array{state: value-of<UrlVerificationState>, lastVerifiedAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'lastVerifiedAt' => $this->lastVerifiedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}

/**
 * @internal
 */
#[Package('framework')]
enum UrlVerificationState: string
{
    case PASSED = 'passed';
    case FAILED = 'failed';
    case PENDING = 'pending';
}
