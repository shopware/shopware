<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Cart\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Content\Media\Struct\MediaBasicStruct;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\LineItem\GoodsInterface;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\LineItem\NestedInterface;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CartPrice;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Rule\Rule;

class CalculatedCartTest extends TestCase
{
    public function testEmptyCartHasNoGoods(): void
    {
        $cart = new CalculatedCart(
            new Cart('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection(),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(0, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartWithLineItemsHasGoods(): void
    {
        $cart = new CalculatedCart(
            new Cart('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new ConfiguredGoods('A', new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()), 1, 'A', 'Label'),
                new TestLineItem('B'),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(1, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartHasNoGoodsIfNoLineItemDefinedAsGoods(): void
    {
        $cart = new CalculatedCart(
            new Cart('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new TestLineItem('A'),
                new TestLineItem('B'),
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        static::assertCount(0, $cart->getCalculatedLineItems()->filterGoods());
    }

    public function testCartWithNestedLineItemHasChildren(): void
    {
        $price = new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $lineItem = new NestedLineItem('X', $price, 1, 'nested',
            new CalculatedLineItemCollection([
                new TestLineItem('B'),
                new TestLineItem('C'),
            ]),
            'test'
        );

        $cart = new CalculatedCart(
            new Cart('test', 'test', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new TestLineItem('A'),
                $lineItem,
            ]),
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new DeliveryCollection()
        );

        $this->assertCount(4, $cart->getCalculatedLineItems()->getFlatElements());
        $this->assertCount(2, $cart->getCalculatedLineItems());
    }
}

class NestedLineItem extends CalculatedLineItem implements NestedInterface
{
    /**
     * @var CalculatedLineItemCollection
     */
    private $children;

    public function __construct(
        string $identifier,
        CalculatedPrice $price,
        int $quantity,
        string $type,
        CalculatedLineItemCollection $children,
        string $label,
        ?LineItemInterface $lineItem = null,
        ?Rule $rule = null
    ) {
        parent::__construct($identifier, $price, $quantity, $type, $label, $lineItem, $rule);
        $this->children = $children;
    }

    public function considerChildrenPrices(): bool
    {
        return false;
    }

    public function getChildren(): CalculatedLineItemCollection
    {
        return $this->children;
    }
}

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

class ConfiguredGoods extends CalculatedLineItem implements GoodsInterface
{
}
