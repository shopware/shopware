<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('data-services')]
readonly class ConsentRevokedEvent
{
    public function __construct(
        public string $consentName,
        public string $consentScope,
        public string $identifier,
        public string $actor,
    ) {
    }
}
