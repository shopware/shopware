<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopware\Core\Checkout\Cart\Rule\ShippingMethodRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingMethodRule::class)]
#[Group('rules')]
class ShippingMethodRuleTest extends TestCase
{
    private const CONTEXT_SHIPPING_METHOD_ID = '0915d54fbf80423c917c61ad5a391b48';

    private const DELIVERY_SHIPPING_METHOD_ID = 'b3f0e4e7c6a84f5f9c3b2d1a0e9f8c7d';

    public function testGetConstraints(): void
    {
        $rule = new ShippingMethodRule();

        static::assertEquals([
            'shippingMethodIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ], $rule->getConstraints());
    }

    public function testConstraintsRejectEmptyShippingMethodIds(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', []);

        $this->assertViolationCode($violations, NotBlank::IS_BLANK_ERROR);
    }

    public function testConstraintsRejectStringShippingMethodIds(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', '0915d54fbf80423c917c61ad5a391b48');

        $this->assertViolationCode($violations, Type::INVALID_TYPE_ERROR);
    }

    public function testConstraintsRejectInvalidShippingMethodIdsUuid(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', [true, 3, null, '0915d54fbf80423c917c61ad5a391b48']);

        $this->assertViolationCode($violations, ArrayOfUuid::INVALID_TYPE_CODE, 3);
    }

    #[DataProvider('validUuidOperators')]
    public function testConstraintsAcceptAvailableOperators(string $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        static::assertCount(0, $violations);
    }

    #[DataProvider('invalidUuidOperators')]
    public function testConstraintsRejectInvalidOperators(mixed $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        $this->assertViolationCode($violations, Choice::NO_SUCH_CHOICE_ERROR);
    }

    public function testConstraintsAcceptValidShippingMethodIds(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', [Uuid::randomHex()]);

        static::assertCount(0, $violations);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function validUuidOperators(): \Generator
    {
        yield 'equals' => [Rule::OPERATOR_EQ];
        yield 'not equals' => [Rule::OPERATOR_NEQ];
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function invalidUuidOperators(): \Generator
    {
        yield 'less than or equals' => [Rule::OPERATOR_LTE];
        yield 'greater than or equals' => [Rule::OPERATOR_GTE];
        yield 'unknown operator' => ['Invalid'];
        yield 'boolean operator' => [true];
        yield 'float operator' => [1.1];
    }

    public function testDeliveryScopeReadsTheShippingMethodOfTheDelivery(): void
    {
        $rule = (new ShippingMethodRule())->assign(['shippingMethodIds' => [self::DELIVERY_SHIPPING_METHOD_ID], 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingMethod')->willReturn($this->createShippingMethod(self::CONTEXT_SHIPPING_METHOD_ID));

        $delivery = $this->createDelivery($this->createShippingMethod(self::DELIVERY_SHIPPING_METHOD_ID));

        static::assertTrue($rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    public function testCartScopeStillReadsTheShippingMethodOfTheContext(): void
    {
        $rule = (new ShippingMethodRule())->assign(['shippingMethodIds' => [self::CONTEXT_SHIPPING_METHOD_ID], 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingMethod')->willReturn($this->createShippingMethod(self::CONTEXT_SHIPPING_METHOD_ID));

        $delivery = $this->createDelivery($this->createShippingMethod(self::DELIVERY_SHIPPING_METHOD_ID));

        static::assertTrue($rule->match(new CartRuleScope(new Cart('test'), $context)));
        static::assertFalse($rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, (new ShippingMethodRule())->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode, int $expectedCount = 1): void
    {
        static::assertCount($expectedCount, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }

    private function createShippingMethod(string $id): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);

        return $shippingMethod;
    }

    private function createDelivery(ShippingMethodEntity $shippingMethod): Delivery
    {
        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
