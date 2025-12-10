<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\DTO\Consent;

#[Package('data-services')]
readonly class ConsentRevokedEvent
{
    public function __construct(public Consent $consent, public string $identifier)
    {
    }
}
