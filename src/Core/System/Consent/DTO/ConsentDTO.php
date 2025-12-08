<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\ConsentType;

class ConsentDTO
{

    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly ConsentType $type,
        public readonly ConsentState $status,
    ) {
    }
}