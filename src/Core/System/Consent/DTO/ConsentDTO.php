<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\DTO;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class ConsentDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $storage,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {
    }
}
