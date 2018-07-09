<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Common;

use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Media\MediaStruct;

class TestLineItem implements CalculatedLineItemInterface
{
    /**
     * @var string
     */
    private $identifier;

    /**
     * @var Price
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
     * @var null|MediaStruct
     */
    private $cover;

    /**
     * @var null|string
     */
    private $description;

    public function __construct(
        string $identifier,
        ?Price $price = null,
        int $quantity = 1,
        string $type = 'test-item',
        string $label = 'Default label',
        ?LineItemInterface $lineItem = null,
        ?MediaStruct $cover = null,
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
            $this->price = new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());
        }
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

    public function getLabel(): string
    {
        $this->label;
    }

    public function getCover(): ?MediaStruct
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
