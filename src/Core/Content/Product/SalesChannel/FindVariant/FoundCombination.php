<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\FindVariant;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @package inventory
 */
class FoundCombination extends Struct
{
    protected string $variantId;

    /**
     * @var array<string>
     */
    protected array $options;

    public function __construct(string $variantId, array $options)
    {
        $this->variantId = $variantId;
        $this->options = $options;
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
