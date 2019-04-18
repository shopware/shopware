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
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsCollector;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsDataDefinition;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Fakes\FakePromotionGateway;
use Shopware\Core\Content\Product\Cart\ProductCollector;
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
    private $promotionScope = null;

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
        $this->cart->add(new LineItem('CODE-123', CartPromotionsCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));
        // add product items
        $this->cart->add(new LineItem('P1', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));
        $this->cart->add(new LineItem('P2', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));

        $this->promotionGlobal = new PromotionEntity();
        $this->promotionGlobal->setId('PROM-GLOBAL');
        $this->promotionGlobal->setPercental(true);
        $this->promotionGlobal->setValue(10);

        $this->promotionPersona = new PromotionEntity();
        $this->promotionPersona->setId('PROM-PERSONA');
        $this->promotionPersona->setPercental(true);
        $this->promotionPersona->setValue(10);
        $this->promotionPersona->setPersonaRules(new RuleCollection([$this->getFakeRule()]));

        $this->promotionScope = new PromotionEntity();
        $this->promotionScope->setId('PROM-SCOPE');
        $this->promotionScope->setPercental(true);
        $this->promotionScope->setValue(10);
        $this->promotionScope->setScopeRule($this->getFakeRule());
    }

    /**
     * This test verifies that our collect function does correctly
     * iterate through all available promotions and does only return the
     * valid promotions in the data struct.
     * Thus we build a fake promotion gateway, that returns 3 promotions
     * with a persona and scope rule restriction and an additional one without any restrictions.
     * Our Checkout Context does not have any rules applied, so our collect
     * function should only return the global promotion in the end.
     *
     * @test
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function testCollectOnlyReturnsValidPromotions()
    {
        $fakePromotionGateway = new FakePromotionGateway(
            [
                $this->promotionPersona,
                $this->promotionScope,
                $this->promotionGlobal,
            ],
            []
        );

        $definitions = new StructCollection();
        $data = new StructCollection();

        $collector = new CartPromotionsCollector($fakePromotionGateway);
        $collector->setFeatureFlagUnlocked(true);

        // make sure we have some prepared placeholders
        $collector->prepare($definitions, $this->cart, $this->checkoutContext, new CartBehavior());

        // collect our valid promotions
        $collector->collect($definitions, $data, $this->cart, $this->checkoutContext, new CartBehavior());

        /** @var CartPromotionsDataDefinition $collectData */
        $collectData = $data->get(CartPromotionsCollector::DATA_KEY);

        /** @var array $collectedPromotions */
        $collectedPromotions = $collectData->getPromotions();

        /** @var PromotionEntity $promotion */
        $promotion = $collectedPromotions[0];

        // now assert that we have only 1 promotion.
        // this one should be the global one
        static::assertEquals(1, count($collectedPromotions));
        static::assertEquals('PROM-GLOBAL', $promotion->getId());
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
        $collector->setFeatureFlagUnlocked(true);

        // add a fake promotion to our definition
        // this one will be added as new promotion line item
        $dataDefinition = new StructCollection();
        $dataDefinition->set(CartPromotionsCollector::DATA_KEY, new CartPromotionsDataDefinition([$this->promotionGlobal]));

        $collector->enrich($dataDefinition, $this->cart, $this->checkoutContext, new CartBehavior());

        /** @var LineItemCollection $promoLineItem */
        $promoLineItem = $this->cart->getLineItems();
        /** @var LineItem $item */
        $item = $promoLineItem->getElements()['PROM-GLOBAL'];

        static::assertEquals('PROM-GLOBAL', $item->getKey());
    }

    private function getFakeRule(): RuleEntity
    {
        $rule = new RuleEntity();
        $rule->setId('R1');

        return $rule;
    }
}
