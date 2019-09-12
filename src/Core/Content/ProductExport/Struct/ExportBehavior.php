<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

class ExportBehavior
{
    /** @var bool */
    private $ignoreCache;

    /** @var bool */
    private $includeInactive;

    public function __construct(bool $ignoreCache = false, bool $includeInactive = false)
    {
        $this->ignoreCache = $ignoreCache;
        $this->includeInactive = $includeInactive;
    }

    public function ignoreCache(): bool
    {
        return $this->ignoreCache;
    }

    public function includeInactive(): bool
    {
        return $this->includeInactive;
    }
}
