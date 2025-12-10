<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;

/**
 * @codeCoverageIgnore
 */
#[Package('data-services')]
readonly class ConsentStateHistoryItem
{
    public function __construct(
        public ConsentStatus $status,
        public ?string $identifier,
        public string $actorId,
        public \DateTimeImmutable $createdAt
    ) {
    }
}
