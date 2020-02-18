<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartHasDeliveryFreeItemRule;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class CartHasFreeDeliveryItemRuleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $conditionRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testIfShippingFreeLineArticlesAreCaught(): void
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add((new LineItem('dummyWithShippingCost', 'product', null, 3))->setDeliveryInformation(
            new DeliveryInformation(
                9999,
                50.0,
                false,
                null,
                (new DeliveryTime())->assign([
                    'min' => 1,
                    'max' => 3,
                    'unit' => 'weeks',
                    'name' => '1-3 weeks',
                ])
            )
        ));
        $lineItemCollection->add(
            (new LineItem('dummyNoShippingCost', 'product', null, 3))->setDeliveryInformation(
                new DeliveryInformation(
                    9999,
                    50.0,
                    true,
                    null,
                    (new DeliveryTime())->assign([
                        'min' => 1,
                        'max' => 3,
                        'unit' => 'weeks',
                        'name' => '1-3 weeks',
                    ])
                )
            )
        );

        $cart->addLineItems($lineItemCollection);

        $rule = new CartHasDeliveryFreeItemRule();

        $match = $rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class)));

        static::assertTrue($match);
    }

    public function testNotContainsFreeDeliveryItems(): void
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add(
            (new LineItem('dummyNoShippingCost', 'product', null, 3))->setDeliveryInformation(
                new DeliveryInformation(
                    9999,
                    50.0,
                    false,
                    null,
                    (new DeliveryTime())->assign([
                        'min' => 1,
                        'max' => 3,
                        'unit' => 'weeks',
                        'name' => '1-3 weeks',
                    ])
                )
            )
        );

        $cart->addLineItems($lineItemCollection);

        $rule = new CartHasDeliveryFreeItemRule();

        $match = $rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class)));

        static::assertFalse($match);
    }

    public function testEmptyDeliveryItems(): void
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $cart->addLineItems($lineItemCollection);

        $rule = new CartHasDeliveryFreeItemRule();
        $match = $rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class)));

        static::assertFalse($match);

        $rule = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => false]);
        $match = $rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class)));

        static::assertTrue($match);
    }

    public function testNotContainsFreeDeliveryItemsMatchesNotAllowed(): void
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add(
            (new LineItem('dummyNoShippingCost', 'product', null, 3))->setDeliveryInformation(
                new DeliveryInformation(
                    9999,
                    50.0,
                    false,
                    null,
                    (new DeliveryTime())->assign([
                        'min' => 1,
                        'max' => 3,
                        'unit' => 'weeks',
                        'name' => '1-3 weeks',
                    ])
                )
            )
        );

        $cart->addLineItems($lineItemCollection);

        $rule = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => false]);

        $match = $rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class)));

        static::assertTrue($match);
    }

    public function testNotContainsFreeDeliveryItemsWithDeliveryFreeItem(): void
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add((new LineItem('dummyWithShippingCost', 'product', null, 3))->setDeliveryInformation(
            new DeliveryInformation(
                9999,
                50.0,
                false,
                null,
                (new DeliveryTime())->assign([
                    'min' => 1,
                    'max' => 3,
                    'unit' => 'weeks',
                    'name' => '1-3 weeks',
                ])
            )
        ));
        $lineItemCollection->add(
            (new LineItem('dummyNoShippingCost', 'product', null, 3))->setDeliveryInformation(
                new DeliveryInformation(
                    9999,
                    50.0,
                    true,
                    null,
                    (new DeliveryTime())->assign([
                        'min' => 1,
                        'max' => 3,
                        'unit' => 'weeks',
                        'name' => '1-3 weeks',
                    ])
                )
            )
        );

        $cart->addLineItems($lineItemCollection);

        $rule = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => false]);

        $match = $rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class)));

        static::assertFalse($match);
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();

        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new CartHasDeliveryFreeItemRule())->getName(),
                'ruleId' => $ruleId,
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    /**
     * @dataProvider getLineItemFreeDeliveryTestData
     */
    public function testLineItemIsFreeDelivery(bool $ruleActive, bool $isFreeDelivery, bool $expected): void
    {
        $lineItem = (new LineItem('dummyWithShippingCost', 'product', null, 3))->setDeliveryInformation(
            new DeliveryInformation(
                9999,
                50.0,
                $isFreeDelivery,
                null,
                (new DeliveryTime())->assign([
                    'min' => 1,
                    'max' => 3,
                    'unit' => 'weeks',
                    'name' => '1-3 weeks',
                ])
            )
        );

        $rule = (new CartHasDeliveryFreeItemRule())->assign(['allowed' => $ruleActive]);

        $match = $rule->match(new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class)));

        static::assertEquals($expected, $match);
    }

    public function getLineItemFreeDeliveryTestData(): array
    {
        return [
            'rule yes / shipping free yes' => [true, true, true],
            'rule yes / shipping free no' => [true, false, false],
            'rule no / shipping free yes' => [false, true, false],
            'rule no / shipping free no' => [false, false, true],
        ];
    }
}
