<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionExclusionTrait;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversTrait(PromotionExclusionTrait::class)]
class PromotionExclusionTraitTest extends TestCase
{
    public function testPreventCombinationExcludesInBothDirections(): void
    {
        $items = new LineItemCollection([
            $this->discountItem('prevent', priority: 1, preventCombination: true),
            $this->discountItem('configured', priority: 1, exclusions: ['other']),
            $this->discountItem('other', priority: 1),
        ]);

        static::assertSame([
            'prevent' => ['configured', 'other'],
            'configured' => ['other', 'prevent'],
            'other' => ['prevent'],
        ], (new ExclusionTraitUser())->buildExclusions($items));
    }

    public function testItemsWithoutScopeAreIgnoredAndMissingExclusionsCountAsNone(): void
    {
        $withoutScope = $this->discountItem('without-scope', priority: 1, exclusions: ['without-exclusions']);
        $withoutScope->removePayloadValue('discountScope');
        $withoutExclusions = $this->discountItem('without-exclusions', priority: 1);
        $withoutExclusions->removePayloadValue('exclusions');

        static::assertSame(
            ['without-exclusions' => []],
            (new ExclusionTraitUser())->buildExclusions(new LineItemCollection([$withoutScope, $withoutExclusions]))
        );
    }

    public function testLaterPromotionOfSamePriorityCannotExcludeEarlierOne(): void
    {
        $first = $this->discountItem('first', priority: 1, exclusions: ['second']);
        $second = $this->discountItem('second', priority: 1, exclusions: ['first']);

        static::assertFalse($this->isExcluded($first, [$first, $second]));
        static::assertTrue($this->isExcluded($second, [$first, $second]));
    }

    public function testExcludedPromotionDoesNotExcludeOthers(): void
    {
        $first = $this->discountItem('first', priority: 3, exclusions: ['second']);
        $second = $this->discountItem('second', priority: 2, exclusions: ['third']);
        $third = $this->discountItem('third', priority: 1);

        static::assertFalse($this->isExcluded($third, [$first, $second, $third]));
    }

    public static function excluderEligibilityProvider(): \Generator
    {
        yield 'failed its requirement when processed, would pass now' => [false, true, false, false];
        yield 'passed its requirement when processed, would fail now' => [true, false, false, true];
        yield 'not processed yet, requirement passes' => [null, true, false, true];
        yield 'not processed yet, requirement fails' => [null, false, false, false];
        yield 'not processed yet, but already in the cart' => [null, false, true, true];
    }

    #[DataProvider('excluderEligibilityProvider')]
    public function testExcluderOnlyCountsWhenEligible(?bool $recordedEligibility, bool $requirementPasses, bool $inCart, bool $expectedExcluded): void
    {
        $excluding = $this->discountItem('excluding', priority: 2, exclusions: ['checked']);
        $checked = $this->discountItem('checked', priority: 1);

        $cart = new Cart('promotion-test');
        if ($inCart) {
            $cart->add($excluding);
        }

        static::assertSame($expectedExcluded, $this->isExcluded(
            $checked,
            [$excluding, $checked],
            eligibility: $recordedEligibility === null ? [] : ['excluding' => $recordedEligibility],
            requirements: ['excluding' => $requirementPasses],
            cart: $cart,
        ));
    }

    /**
     * @param list<LineItem> $sortedItems
     * @param array<string, bool> $eligibility
     * @param array<string, bool> $requirements
     */
    private function isExcluded(LineItem $checked, array $sortedItems, array $eligibility = [], array $requirements = [], ?Cart $cart = null): bool
    {
        $items = new LineItemCollection($sortedItems);
        $user = new ExclusionTraitUser($requirements);

        return $user->isExcluded(
            $checked,
            $items,
            $user->buildExclusions($items),
            $eligibility,
            $cart ?? new Cart('promotion-test'),
            static::createStub(SalesChannelContext::class)
        );
    }

    /**
     * @param list<string> $exclusions
     */
    private function discountItem(string $promotionId, int $priority, array $exclusions = [], bool $preventCombination = false): LineItem
    {
        return (new LineItem($promotionId, PromotionProcessor::LINE_ITEM_TYPE))
            ->setPayloadValue('promotionId', $promotionId)
            ->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART)
            ->setPayloadValue('priority', $priority)
            ->setPayloadValue('exclusions', $exclusions)
            ->setPayloadValue('preventCombination', $preventCombination);
    }
}

/**
 * @internal
 */
class ExclusionTraitUser
{
    use PromotionExclusionTrait {
        buildExclusions as public;
        isExcluded as public;
    }

    /**
     * @param array<string, bool> $requirements requirement check result per line item id
     */
    public function __construct(private readonly array $requirements = [])
    {
    }

    protected function isRequirementValid(LineItem $lineItem, Cart $calculated, SalesChannelContext $context): bool
    {
        return $this->requirements[$lineItem->getId()] ?? true;
    }
}
