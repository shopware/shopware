<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;

/**
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class ConsentStateRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly ConsentStatus $status,
        public readonly string $actor,
        public readonly string $updatedAt,
    ) {
    }
}
