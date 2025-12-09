<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\System\Consent\ConsentState;

class ConsentStateDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly ConsentState $status,
    ) {}
}