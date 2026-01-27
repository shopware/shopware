<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentStatus;

/**
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class ConsentState
{
    public function __construct(
        public readonly string $name,
        public readonly string $scopeName,
        public readonly string $identifier,
        public readonly ConsentStatus $status,
        public readonly ?string $actor,
        public readonly ?string $updatedAt
    ) {
    }

    public static function fromDefinitionAndRecord(ConsentDefinition $consent, ConsentStateRecord $record): self
    {
        return new self($consent->getName(), $consent->getScopeName(), $record->identifier, $record->status, $record->actor, $record->updatedAt);
    }
}
