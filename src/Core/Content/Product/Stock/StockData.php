<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
final class StockData extends Struct
{
    public function __construct(
        public readonly string $productId,
        public readonly int $stock,
        public readonly bool $available,
        public readonly ?int $minPurchase = null,
        public readonly ?int $maxPurchase = null,
        public readonly ?bool $isCloseout = null,
    ) {
    }

    /**
     * @param array{productId: string, stock: int, available: bool, minPurchase?: int, maxPurchase?: int, isCloseout?: bool, extraData?: array<mixed>} $info
     */
    public static function fromArray(array $info): self
    {
        return new self(
            $info['productId'],
            $info['stock'],
            $info['available'],
            $info['minPurchase'] ?? null,
            $info['maxPurchase'] ?? null,
            $info['isCloseout'] ?? null,
        );
    }
}
