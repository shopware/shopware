<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem;

use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Media\Struct\MediaBasicStruct;

class CustomLineItem implements CalculatedLineItemInterface
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
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $label;

    public function __construct(
        string $identifier,
        CalculatedPrice $price,
        int $quantity,
        string $type,
        string $label
    ) {
        $this->identifier = $identifier;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->type = $type;
        $this->label = $label;
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

    public function getLineItem(): ? LineItemInterface
    {
        return null;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCover(): ?MediaBasicStruct
    {
        return null;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function jsonSerialize()
    {
    }
}
