<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class ExportBehavior
{
    public function __construct(
        private readonly bool $ignoreCache = false,
        private readonly bool $includeInactive = false,
        private readonly bool $batchMode = false,
        private readonly bool $generateHeader = true,
        private readonly bool $generateFooter = true,
        private readonly int $offset = 0
    ) {
    }

    public function ignoreCache(): bool
    {
        return $this->ignoreCache;
    }

    public function includeInactive(): bool
    {
        return $this->includeInactive;
    }

    public function batchMode(): bool
    {
        return $this->batchMode;
    }

    public function generateHeader(): bool
    {
        return $this->generateHeader;
    }

    public function generateFooter(): bool
    {
        return $this->generateFooter;
    }

    public function offset(): int
    {
        return $this->offset;
    }
}
