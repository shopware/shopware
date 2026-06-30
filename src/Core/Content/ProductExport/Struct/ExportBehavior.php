<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ExportBehavior
{
    /**
     * Keyset cursor: the highest product.autoIncrement already exported. 0 starts a fresh run.
     */
    private readonly int $lastId;

    public function __construct(
        private readonly bool $ignoreCache = false,
        private readonly bool $includeInactive = false,
        private readonly bool $batchMode = false,
        private readonly bool $generateHeader = true,
        private readonly bool $generateFooter = true,
        int $offset = 0
    ) {
        $this->lastId = $offset;
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

    /**
     * @deprecated tag:v6.8.0 - Use lastId() instead. The value is now the keyset cursor
     * (highest product.autoIncrement already exported), not a row offset.
     */
    public function offset(): int
    {
        return $this->lastId;
    }

    public function lastId(): int
    {
        return $this->lastId;
    }
}
