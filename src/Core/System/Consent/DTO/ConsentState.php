<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentStatus;
use Symfony\Component\Clock\Clock;

#[Package('data-services')]
class ConsentState
{
    public readonly ?string $acceptedUntil;

    public readonly ?string $acceptedRevision;

    public function __construct(
        public readonly string $name,
        public readonly string $scopeName,
        public readonly string $identifier,
        public readonly ConsentStatus $status,
        public readonly ?string $actor,
        public readonly ?string $updatedAt,
        ?string $acceptedRevision = null,
        public readonly ?string $latestRevision = null,
    ) {
        $this->acceptedRevision = $status === ConsentStatus::ACCEPTED ? $acceptedRevision : null;
        $this->acceptedUntil = $this->computeAcceptedUntil();
    }

    public static function fromDefinitionAndRecord(ConsentDefinition $consent, ConsentStateRecord $record): self
    {
        return new self(
            $consent->getName(),
            $consent->getScopeName(),
            $record->identifier,
            $record->status,
            $record->actor,
            $record->updatedAt,
            $record->revision,
            $consent->getLatestRevision(),
        );
    }

    public function isAccepted(): bool
    {
        if ($this->status !== ConsentStatus::ACCEPTED) {
            return false;
        }

        return true;
    }

    public function isCurrent(): bool
    {
        if (!$this->isAccepted()) {
            return false;
        }

        if ($this->latestRevision === null) {
            return true;
        }

        return $this->acceptedRevision === $this->latestRevision;
    }

    public function isRevoked(): bool
    {
        return $this->status === ConsentStatus::REVOKED;
    }

    /**
     * Whether the consent was accepted but on an older revision. It's up to consumers whether this is important.
     */
    public function isStale(): bool
    {
        if (!$this->isAccepted()) {
            return false;
        }

        if ($this->latestRevision === null) {
            return false;
        }

        return $this->acceptedRevision !== $this->latestRevision;
    }

    private function computeAcceptedUntil(): ?string
    {
        return match ($this->status) {
            ConsentStatus::ACCEPTED => Clock::get()->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ConsentStatus::REVOKED => $this->updatedAt,
            default => null,
        };
    }
}
