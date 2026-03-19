<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentStatus;

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

    /**
     * @param $requireLatestRevision bool - When true a consent is only considered accepted if the accepted revision matches the latest revision
     */
    public function isAccepted(bool $requireLatestRevision = false): bool
    {
        if ($this->status !== ConsentStatus::ACCEPTED) {
            return false;
        }

        if ($requireLatestRevision) {
            return $this->acceptedRevision === $this->latestRevision;
        }

        return true;
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
            ConsentStatus::ACCEPTED => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ConsentStatus::REVOKED => $this->updatedAt,
            default => null,
        };
    }
}
