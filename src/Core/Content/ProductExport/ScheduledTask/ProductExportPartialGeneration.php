<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('inventory')]
class ProductExportPartialGeneration implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $productExportId,
        private readonly string $salesChannelId,
        private readonly int $lastId = 0
    ) {
    }

    /**
     * Keyset cursor: the highest product.autoIncrement already exported. 0 starts a fresh run.
     */
    public function getLastId(): int
    {
        return $this->lastId;
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
