<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Framework\Struct\Struct;

class FoundCombination extends Struct
{
    /**
     * @var string
     */
    protected $variantId;

    /**
     * @var string[]
     */
    protected $options;

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
