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

    public function __construct(
        public readonly string $name,
        public readonly string $scopeName,
        public readonly string $identifier,
        public readonly ConsentStatus $status,
        public readonly ?string $actor,
        public readonly ?string $updatedAt
    ) {
        $this->acceptedUntil = $this->computeAcceptedUntil();
    }

    public static function fromDefinitionAndRecord(ConsentDefinition $consent, ConsentStateRecord $record): self
    {
        return new self($consent->getName(), $consent->getScopeName(), $record->identifier, $record->status, $record->actor, $record->updatedAt);
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
