<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionDiscountEntity::class)]
class PromotionDiscountEntityTest extends TestCase
{
    #[DataProvider('scopeSetGroupProvider')]
    #[TestDox('isScopeSetGroup and getSetGroupId parse the setgroup scope')]
    public function testScopeSetGroupHandling(string $scope, bool $isSetGroup, string $expectedGroupId): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setScope($scope);

        static::assertSame($isSetGroup, $discount->isScopeSetGroup());
        static::assertSame($expectedGroupId, $discount->getSetGroupId());
    }

    /**
     * @return \Generator<string, array{0: string, 1: bool, 2: string}>
     */
    public static function scopeSetGroupProvider(): \Generator
    {
        yield 'cart scope is not a setgroup' => [PromotionDiscountEntity::SCOPE_CART, false, ''];
        yield 'plain setgroup without id is not a prefixed setgroup' => [PromotionDiscountEntity::SCOPE_SETGROUP, false, ''];
        yield 'prefixed setgroup exposes the group id' => ['setgroup-abc123', true, 'abc123'];
    }

    #[DataProvider('keyFallbackProvider')]
    #[TestDox('sorter, applier and usage keys fall back to an empty string when null')]
    public function testNullableKeysFallBackToEmptyString(?string $value, string $expected): void
    {
        $discount = new PromotionDiscountEntity();

        $discount->setSorterKey($value);
        $discount->setApplierKey($value);
        $discount->setUsageKey($value);

        static::assertSame($expected, $discount->getSorterKey());
        static::assertSame($expected, $discount->getApplierKey());
        static::assertSame($expected, $discount->getUsageKey());
    }

    /**
     * @return \Generator<string, array{0: ?string, 1: string}>
     */
    public static function keyFallbackProvider(): \Generator
    {
        yield 'null becomes empty string' => [null, ''];
        yield 'value is passed through' => ['custom-key', 'custom-key'];
    }

    public function testGetPickerKeyCastsNullToEmptyString(): void
    {
        $discount = new PromotionDiscountEntity();

        static::assertSame('', $discount->getPickerKey());

        $discount->setPickerKey('picker');
        static::assertSame('picker', $discount->getPickerKey());
    }

    public function testRepresentativeGettersAndSetters(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(15.5);
        $discount->setConsiderAdvancedRules(true);
        $discount->setMaxValue(50.0);

        static::assertSame(PromotionDiscountEntity::TYPE_PERCENTAGE, $discount->getType());
        static::assertSame(15.5, $discount->getValue());
        static::assertTrue($discount->isConsiderAdvancedRules());
        static::assertSame(50.0, $discount->getMaxValue());
    }
}
