<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Cart;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsCollector;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsDataDefinition;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Fakes\FakePromotionGateway;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartPromotionsCollectorTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $checkoutContext = null;

    /**
     * @var Cart
     */
    private $cart = null;

    /**
     * @var PromotionEntity
     */
    private $promotionGlobal = null;

    /**
     * @var PromotionEntity
     */
    private $promotionPersona = null;

    /**
     * @var PromotionEntity
     */
    private $promotionCart = null;

    /**
     * @throws \ReflectionException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     */
    public function setUp(): void
    {
        $this->checkoutContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        $this->cart = new Cart('C1', 'TOKEN-1');
        // add a prepared placeholder promotion
        $this->cart->add(new LineItem('CODE-123', CartPromotionsCollector::LINE_ITEM_TYPE, 1));
        // add product items
        $this->cart->add(new LineItem('P1', LineItem::PRODUCT_LINE_ITEM_TYPE, 1));
        $this->cart->add(new LineItem('P2', LineItem::PRODUCT_LINE_ITEM_TYPE, 1));

        $this->promotionGlobal = new PromotionEntity();
        $this->promotionGlobal->setId('PROM-GLOBAL');
        $this->promotionGlobal->setMaxRedemptionsGlobal(100);
        $this->promotionGlobal->setOrderCount(0);
        $discount1 = new PromotionDiscountEntity();
        $discount1->setId('D1');
        $discount1->setValue(100);
        $discount1->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount1->setScope(PromotionDiscountEntity::SCOPE_CART);
        $this->promotionGlobal->setDiscounts(new PromotionDiscountCollection([$discount1]));

        $this->promotionPersona = new PromotionEntity();
        $this->promotionPersona->setId('PROM-PERSONA');
        $this->promotionPersona->setMaxRedemptionsGlobal(100);
        $this->promotionPersona->setOrderCount(0);
        $this->promotionPersona->setPersonaRules(new RuleCollection([$this->getFakeRule()]));
        $discount2 = new PromotionDiscountEntity();
        $discount2->setId('D2');
        $discount2->setValue(100);
        $discount2->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount2->setScope(PromotionDiscountEntity::SCOPE_CART);
        $this->promotionPersona->setDiscounts(new PromotionDiscountCollection([$discount2]));

        $this->promotionCart = new PromotionEntity();
        $this->promotionCart->setId('PROM-CART');
        $this->promotionCart->setMaxRedemptionsGlobal(100);
        $this->promotionCart->setOrderCount(0);
        $this->promotionCart->setCartRules(new RuleCollection([$this->getFakeRule()]));
        $discount3 = new PromotionDiscountEntity();
        $discount3->setId('D3');
        $discount3->setValue(100);
        $discount3->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount3->setScope(PromotionDiscountEntity::SCOPE_CART);
        $this->promotionCart->setDiscounts(new PromotionDiscountCollection([$discount3]));
    }

    /**
     * This test verifies that our collect function returns all promotions from the gateway.
     * No additional assertion of soft conditions are allowed.
     * These do all need to be inside the Requirements of the Line Item.
     *
     * @test
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function testCollectReturnsAllGatewayPromotions()
    {
        $fakePromotionGateway = new FakePromotionGateway(
            [
                $this->promotionPersona,
                $this->promotionCart,
            ],
            []
        );

        $definitions = new StructCollection();
        $data = new StructCollection();

        $collector = new CartPromotionsCollector($fakePromotionGateway);

        // make sure we have some prepared placeholders
        $collector->prepare($definitions, $this->cart, $this->checkoutContext, new CartBehavior());

        // collect our valid promotions
        $collector->collect($definitions, $data, $this->cart, $this->checkoutContext, new CartBehavior());

        /** @var CartPromotionsDataDefinition $collectData */
        $collectData = $data->get(CartPromotionsCollector::DATA_KEY);

        /** @var array $collectedPromotions */
        $collectedPromotions = $collectData->getPromotions();

        // now assert that we have both promotions
        // with the correct IDs.
        static::assertEquals(2, count($collectedPromotions));
        static::assertEquals('PROM-PERSONA', $collectedPromotions[0]->getId());
        static::assertEquals('PROM-CART', $collectedPromotions[1]->getId());
    }

    /**
     * This test verifies that our cart is correctly enriched with the
     * provided definition data from a previous collect function.
     * We simulate a definition data by using a faked Promotion Entity
     * and pass that one on to the enrich function, along with our actual cart.
     * After this step, we retrieve the cart line items, and access our promotion
     * item with our faked ID from our promotion.
     * If we have this item and the key matches then we know that the enrichment process worked properly.
     *
     * @test
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     */
    public function testEnrichWithPromotionLineItem()
    {
        $fakePromotionGateway = new FakePromotionGateway([], []);

        $collector = new CartPromotionsCollector($fakePromotionGateway);

        // add a fake promotion to our definition
        // this one will be added as new promotion line item
        $dataDefinition = new StructCollection();
        $dataDefinition->set(CartPromotionsCollector::DATA_KEY, new CartPromotionsDataDefinition([$this->promotionGlobal]));

        $collector->enrich($dataDefinition, $this->cart, $this->checkoutContext, new CartBehavior());

        /** @var LineItemCollection $promoLineItem */
        $promoLineItem = $this->cart->getLineItems();

        // discount of promotion 1 PROM-GLOBAL should exist (D1)
        /** @var LineItem $item */
        $item = $promoLineItem->getElements()['D1'];

        static::assertEquals('D1', $item->getKey());
    }

    private function getFakeRule(): RuleEntity
    {
        $rule = new RuleEntity();
        $rule->setId('R1');

        return $rule;
    }
}
