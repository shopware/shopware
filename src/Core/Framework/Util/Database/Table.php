<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Table
{
    /**
     * @param list<string> $columnNames
     */
    public function __construct(
        public array $columnNames
    ) {
    }
}
