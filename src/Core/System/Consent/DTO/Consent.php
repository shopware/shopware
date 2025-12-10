<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class Consent
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ConsentScope $scope,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {
    }
}
