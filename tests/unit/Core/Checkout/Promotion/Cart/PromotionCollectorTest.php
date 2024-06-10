<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedAutomaticPromotions;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedGlobalCodePromotions;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('buyers-experience')]
#[CoversClass(PromotionCollector::class)]
final class PromotionCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->gateway = $this->createMock(PromotionGatewayInterface::class);
        $this->itemBuilder = new PromotionItemBuilder();
        $this->htmlSanitizer = $this->createMock(HtmlSanitizer::class);

        $this->promotionCollector = new PromotionCollector(
            $this->gateway,
            $this->itemBuilder,
            $this->htmlSanitizer,
        );

        $this->cart = $this->createMock(Cart::class);
        $this->context = $this->createMock(SalesChannelContext::class);
    }

    public function testCollectWithExistingPromotionAndDifferentDiscount(): void
    {
        // Arrange
        $original = new Cart('16d71f1837774b6790cc841c5e213c06');
        $lineItem = new LineItem('ba3bf911830d4fdfa182bb7c8e89ec71', LineItem::PRODUCT_LINE_ITEM_TYPE, '54873487dacd4ba0a6e567677002cc02');
        $lineItem2 = new LineItem($lineItem2Id = '77a4e83ed44a4b22993a675010347075', LineItem::DISCOUNT_LINE_ITEM, $code = 'promotions-code');
        $lineItem2->setPayloadValue('discountId', $discountId1 = '7f659f15b82c40049bef621631ac5335');
        $lineItem2->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($lineItem2Id));
        $original->setLineItems(new LineItemCollection([$lineItem, $lineItem2]));

        $promotion = new PromotionEntity();
        $promotion->setId($promotionId = '16d71f1837774b6790cc841c5e213c06');
        $promotion->setCode($code);
        $promotion->setUseIndividualCodes(true);
        $promotion->setPriority(1);

        $discount1 = new PromotionDiscountEntity();
        $discount1->setId($discountId1);
        $discount1->setScope(PromotionDiscountEntity::SCOPE_CART);
        $discount1->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount1->setValue(10.0);
        $discount1->setPromotionId($promotion->getId());

        $discount2 = new PromotionDiscountEntity();
        $discount2->setId($discountId2 = '03fe425351754bffa2ceab9607883e96');
        $discount2->setScope(PromotionDiscountEntity::SCOPE_CART);
        $discount2->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount2->setValue(15.0);
        $discount2->setPromotionId($promotion->getId());

        $promotion->setDiscounts(new PromotionDiscountCollection([$discount1, $discount2]));

        $promotionData = new CartExtension();
        $promotionData->addCode($code);
        $original->addExtension(CartExtension::KEY, $promotionData);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId = 'd3d1d39d521f45c7895ff575b2c23e1e');
        $this->context->method('getSalesChannel')->willReturn($salesChannel);

        $criteria1 = (new Criteria())->addFilter(new PermittedGlobalCodePromotions([$code], $salesChannelId));
        $criteria1->addAssociations([
            'personaRules',
            'personaCustomers',
            'cartRules',
            'orderRules',
            'discounts.discountRules',
            'discounts.promotionDiscountPrices',
            'setgroups',
            'setgroups.setGroupRules'
        ]);

        $criteria2 = (new Criteria())->addFilter(new PermittedAutomaticPromotions($salesChannelId));
        $criteria2->addAssociations([
            'personaRules',
            'personaCustomers',
            'cartRules',
            'orderRules',
            'discounts.discountRules',
            'discounts.promotionDiscountPrices',
            'setgroups',
            'setgroups.setGroupRules'
        ]);

        $this->gateway->method('get')
            ->willReturnCallback(static function ($criteria) use ($criteria1, $criteria2, $promotion) {
                if ($criteria == $criteria1) {
                    return new PromotionCollection([$promotion]);
                }
                if ($criteria == $criteria2) {
                    return new PromotionCollection();
                }
            });

        // Act
        $this->promotionCollector->collect($data = new CartDataCollection(), $original, $this->context, new CartBehavior());

        // Assert
        $promotions = $data->get(PromotionProcessor::DATA_KEY);
        $this->assertCount(2, $promotions);
        $this->assertSame($promotionId, $promotions->first()->getPayloadValue('promotionId'));
        $this->assertSame($discountId1, $promotions->first()->getPayloadValue('discountId'));
        $this->assertNotNull($promotions->first()->getExtension(OrderConverter::ORIGINAL_ID));

        $this->assertSame($promotionId, $promotions->last()->getPayloadValue('promotionId'));
        $this->assertSame($discountId2, $promotions->last()->getPayloadValue('discountId'));
        $this->assertNull($promotions->last()->getExtension(OrderConverter::ORIGINAL_ID));
    }
}
