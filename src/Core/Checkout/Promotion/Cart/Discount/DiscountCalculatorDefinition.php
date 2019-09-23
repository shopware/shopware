<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;

class DiscountCalculatorDefinition
{
    /**
     * @var string
     */
    private $label;

    /**
     * @var PriceDefinitionInterface
     */
    private $priceDefinition;

    /**
     * @var array
     */
    private $payload;

    /**
     * @var string|null
     */
    private $code;

    /**
     * @var LineItemQuantity[]
     */
    private $itemDefinitions;

    /**
     * @param LineItemQuantity[] $itemDefinitions
     */
    public function __construct(string $label, PriceDefinitionInterface $priceDefinition, array $payload, ?string $code, array $itemDefinitions)
    {
        $this->label = $label;
        $this->priceDefinition = $priceDefinition;
        $this->payload = $payload;
        $this->code = $code;
        $this->itemDefinitions = $itemDefinitions;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPriceDefinition(): PriceDefinitionInterface
    {
        return $this->priceDefinition;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function hasItem(string $id): bool
    {
        /** @var LineItemQuantity $item */
        foreach ($this->itemDefinitions as $item) {
            if ($item->getLineItemId() === $id) {
                return true;
            }
        }

        return false;
    }

    public function getItem(string $id): LineItemQuantity
    {
        /** @var LineItemQuantity $item */
        foreach ($this->itemDefinitions as $item) {
            if ($item->getLineItemId() === $id) {
                return $item;
            }
        }

        // todo mache tuen du exception
    }
}
