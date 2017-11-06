<?php

namespace Shopware\Cart\LineItem;

use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Rule\Rule;
use Shopware\Cart\Rule\Validatable;
use Shopware\Framework\Struct\Struct;

class CalculatedLineItem extends Struct implements CalculatedLineItemInterface, Validatable
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var Price
     */
    protected $price;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var LineItemInterface|null
     */
    protected $lineItem;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var null|Rule
     */
    protected $rule;

    public function __construct(
        string $identifier,
        Price $price,
        int $quantity,
        string $type,
        ?LineItemInterface $lineItem = null,
        ?Rule $rule = null
    ) {
        $this->identifier = $identifier;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->lineItem = $lineItem;
        $this->type = $type;
        $this->rule = $rule;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPrice(): Price
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

    public function getRule(): ?Rule
    {
        return $this->rule;
    }
}