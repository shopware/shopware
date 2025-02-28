<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\FindVariant;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class FoundCombination extends Struct
{
    protected string $variantId;

    /**
     * @param string[] $options
     */
    public function __construct(
        protected ProductEntity $variant,
        protected array $options
    ) {
        $this->variantId = $variant->getId();
    }

    public function getVariant(): ProductEntity
    {
        return $this->variant;
    }

    public function getVariantId(): string
    {
        return $this->variant->getId();
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
