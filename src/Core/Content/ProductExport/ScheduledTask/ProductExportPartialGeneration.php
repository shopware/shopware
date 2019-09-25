<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

class ProductExportPartialGeneration
{
    /** @var int */
    private $offset;

    public function __construct(?int $offset = 0)
    {
        $this->offset = $offset;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}
