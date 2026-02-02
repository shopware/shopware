<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Column
{
    public function __construct(
        public string $type,
        public ?int $length,
        public bool $isNotNull,
        public mixed $defaultValue,
    ) {
    }
}
