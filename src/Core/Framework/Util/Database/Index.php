<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Doctrine\DBAL\Schema\Index as DbalIndex;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Index
{
    public function __construct(
        public string $name,
        public string $type,
    ) {
    }

    public static function createFromDbalIndex(DbalIndex $dbalIndex): self
    {
        return new Index(
            name: $dbalIndex->getObjectName()->getIdentifier()->getValue(),
            type: $dbalIndex->getType()->name,
        );
    }
}
