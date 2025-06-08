<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\FindVariant;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class FoundCombination extends Struct
{
    /**
     * @param string[] $options
     */
    public function __construct(
        protected string $variantId,
        protected array $options
    ) {
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
