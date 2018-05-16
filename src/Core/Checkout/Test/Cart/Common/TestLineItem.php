<?php

namespace Shopware\Checkout\Test\Cart\Common;

use Shopware\Content\Media\Struct\MediaBasicStruct;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Checkout\Cart\LineItem\LineItemInterface;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class TestLineItem implements CalculatedLineItemInterface
{
    /**
     * @var string
     */
    private $identifier;

    /**
     * @var CalculatedPrice
     */
    private $price;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var null|LineItemInterface
     */
    private $lineItem;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $label;

    /**
     * @var null|MediaBasicStruct
     */
    private $cover;

    /**
     * @var null|string
     */
    private $description;

    public function __construct(
        string $identifier,
        ?CalculatedPrice $price = null,
        int $quantity = 1,
        string $type = 'test-item',
        string $label = 'Default label',
        ?LineItemInterface $lineItem = null,
        ?MediaBasicStruct $cover = null,
        ?string $description = null
    ) {
        $this->identifier = $identifier;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->lineItem = $lineItem;
        $this->type = $type;
        $this->label = $label;
        $this->cover = $cover;
        $this->description = $description;

        if (!$this->price) {
            $this->price = new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getLineItem(): ?LineItemInterface
    {
        return $this->lineItem;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        $this->label;
    }

    public function getCover(): ?MediaBasicStruct
    {
        return $this->cover;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function jsonSerialize()
    {
    }
}