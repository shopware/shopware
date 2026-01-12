<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\DbTableHelper;

use Shopware\Core\Framework\Log\Package;

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
