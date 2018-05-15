<?php declare(strict_types=1);

namespace Shopware\Checkout\Cart\LineItem;

use Shopware\Content\Media\Struct\MediaBasicStruct;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Application\Context\Rule\Rule;
use Shopware\Application\Context\Rule\Validatable;
use Shopware\Framework\Struct\Struct;

class CalculatedLineItem extends Struct implements CalculatedLineItemInterface, Validatable
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var CalculatedPrice
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
     * @var string
     */
    protected $label;

    /**
     * @var MediaBasicStruct|null
     */
    protected $cover;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var Rule|null
     */
    protected $rule;

    public function __construct(
        string $identifier,
        CalculatedPrice $price,
        int $quantity,
        string $type,
        string $label,
        ?LineItemInterface $lineItem = null,
        ?Rule $rule = null,
        ?string $description = '',
        ?MediaBasicStruct $cover = null
    ) {
        $this->identifier = $identifier;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->type = $type;
        $this->label = $label;
        $this->lineItem = $lineItem;
        $this->description = $description;
        $this->cover = $cover;
        $this->rule = $rule;
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

    public function getRule(): ?Rule
    {
        return $this->rule;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCover(): ?MediaBasicStruct
    {
        return $this->cover;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
