<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

class ProductExportPartialGeneration
{
    /**
     * @var int
     */
    private $offset;

    /**
     * @var string
     */
    private $productExportId;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(string $productExportId, string $salesChannelId, int $offset = 0)
    {
        $this->offset = $offset;
        $this->productExportId = $productExportId;
        $this->salesChannelId = $salesChannelId;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getProductExportId(): string
    {
        return $this->productExportId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
