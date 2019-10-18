<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

class ExportBehavior
{
    /** @var bool */
    private $ignoreCache;

    /** @var bool */
    private $includeInactive;

    /** @var bool */
    private $batchMode;

    /** @var bool */
    private $generateHeader;

    /** @var bool */
    private $generateFooter;

    /** @var int */
    private $offset;

    public function __construct(
        bool $ignoreCache = false,
        bool $includeInactive = false,
        bool $batchMode = false,
        bool $generateHeader = true,
        bool $generateFooter = true,
        int $offset = 0
    ) {
        $this->ignoreCache = $ignoreCache;
        $this->includeInactive = $includeInactive;
        $this->batchMode = $batchMode;
        $this->generateHeader = $generateHeader;
        $this->generateFooter = $generateFooter;
        $this->offset = $offset;
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
