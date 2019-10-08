<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

class ExportBehavior
{
    /** @var bool */
    private $ignoreCache;

    /** @var bool */
    private $includeInactive;

    /** @var bool */
    private $preview;

    public function __construct(bool $ignoreCache = false, bool $includeInactive = false, $preview = false)
    {
        $this->ignoreCache = $ignoreCache;
        $this->includeInactive = $includeInactive;
        $this->preview = $preview;
    }

    public function ignoreCache(): bool
    {
        return $this->ignoreCache;
    }

    public function includeInactive(): bool
    {
        return $this->includeInactive;
    }

    public function preview(): bool
    {
        return $this->preview;
    }
}
