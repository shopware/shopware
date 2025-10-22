<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTagRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[CoversClass(LineItemTagRule::class)]
class LineItemTagRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    public function testLineItemNoMatchWithoutTags(): void
    {
        $match = $this->createLineItemTagRule([Uuid::randomHex()])->match(
            new LineItemScope(self::createLineItem(), $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);
    }

    public function testLineItemMatchUnequalsTags(): void
    {
        $match = $this->createLineItemTagRule([Uuid::randomHex()], Rule::OPERATOR_NEQ)->match(
            new LineItemScope(self::createLineItem(), $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testLineItemMatchWithMatchingTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $lineItem = self::createLineItem()->replacePayload(['tagIds' => $tagIds]);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testLineItemMatchWithPartialMatchingTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $lineItem = self::createLineItem()->replacePayload(['tagIds' => [$tagIds[0]]]);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testLineItemNoMatchWithPartialMatchingUnequalOperatorTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $lineItem = self::createLineItem()->replacePayload(['tagIds' => [$tagIds[0]]]);

        $match = $this->createLineItemTagRule($tagIds, Rule::OPERATOR_NEQ)->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);
    }

    public function testCartNoMatchWithoutTags(): void
    {
        $lineItemCollection = new LineItemCollection([
            self::createLineItem(),
            self::createLineItem(),
        ]);
        $cart = self::createCart($lineItemCollection);

        $match = $this->createLineItemTagRule([Uuid::randomHex()])->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);
    }

    public function testCartMatchUnequalsTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            self::createLineItem()->replacePayload(['tagIds' => [$tagIds[1]]]),
            self::createLineItem()->replacePayload(['tagIds' => [$tagIds[2]]]),
        ]);
        $cart = self::createCart($lineItemCollection);

        $match = $this->createLineItemTagRule([$tagIds[0]], Rule::OPERATOR_NEQ)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartMatchEqualsTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            self::createLineItem()->replacePayload(['tagIds' => [$tagIds[0], $tagIds[1]]]),
            self::createLineItem()->replacePayload(['tagIds' => [$tagIds[2]]]),
        ]);
        $cart = self::createCart($lineItemCollection);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartMatchEqualsTagsNested(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            self::createLineItem()->replacePayload(['tagIds' => [$tagIds[0], $tagIds[1]]]),
            self::createLineItem()->replacePayload(['tagIds' => [$tagIds[2]]]),
        ]);
        $containerLineItem = self::createContainerLineItem($lineItemCollection);
        $cart = self::createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->createLineItemTagRule($tagIds)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartMatchPartialWithMatchingTag(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            self::createLineItem(),
            self::createLineItem()->replacePayload(['tagIds' => $tagIds]),
        ]);
        $cart = self::createCart($lineItemCollection);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartNoMatchWithPartialMatchingUnequalOperatorTag(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            self::createLineItem()->replacePayload(['tagIds' => [$tagIds[0]]]),
        ]);
        $cart = self::createCart($lineItemCollection);

        $match = $this->createLineItemTagRule($tagIds, Rule::OPERATOR_NEQ)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);

        $lineItemCollection->add(self::createLineItem());
        $cart = self::createCart($lineItemCollection);

        $match = $this->createLineItemTagRule($tagIds, Rule::OPERATOR_NEQ)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = (new LineItemTagRule())->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        static::assertArrayHasKey('identifiers', $ruleConstraints, 'Constraint identifiers not found in Rule');
        $identifiers = $ruleConstraints['identifiers'];
        static::assertEquals(new NotBlank(), $identifiers[0]);
        static::assertEquals(new ArrayOfUuid(), $identifiers[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, ?string $tag, bool $withItemWithoutPayload = true): void
    {
        $identifiers = ['kyln123', 'kyln456'];
        if ($tag !== null) {
            $lineItems = [
                self::createLineItem()->replacePayload(['tagIds' => [$tag]]),
            ];

            if ($withItemWithoutPayload) {
                $lineItems[] = self::createLineItem();
            }
        } else {
            $lineItems = [
                self::createLineItem(),
            ];
        }

        $lineItemCollection = new LineItemCollection($lineItems);
        $cart = self::createCart($lineItemCollection);

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));
        $rule = (new LineItemTagRule())->assign(['identifiers' => $identifiers, 'operator' => $operator]);

        $match = $rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return array<string, array<string|bool|null>>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_oq / not match / tagId' => [Rule::OPERATOR_EQ, false, 'kyln000'],
            'operator_oq / match / tagId' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / tagId' => [Rule::OPERATOR_NEQ, true, 'kyln000'],
            'operator_neq / not match / tagId' => [Rule::OPERATOR_NEQ, false, 'kyln123', false],
            'operator_empty / not match / tagId' => [Rule::OPERATOR_EMPTY, false, 'kyln123', false],
            'operator_empty / match / tagId' => [Rule::OPERATOR_EMPTY, true, null],
            'operator_neq / match / tagId and item without tag' => [Rule::OPERATOR_NEQ, true, 'kyln123'],
            'operator_empty / match / tagId and item without tag' => [Rule::OPERATOR_EMPTY, true, 'kyln123'],
        ];
    }

    /**
     * @param array<string> $tagIds
     */
    private function createLineItemTagRule(array $tagIds, string $operator = Rule::OPERATOR_EQ): LineItemTagRule
    {
        return (new LineItemTagRule())->assign(['operator' => $operator, 'identifiers' => $tagIds]);
    }
}
