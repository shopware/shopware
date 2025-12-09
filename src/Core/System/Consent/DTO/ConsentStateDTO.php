<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentState;

/**
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class ConsentStateDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly ConsentState $status,
    ) {
    }
}
