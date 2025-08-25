<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackageRules;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AdvancedPackageRules::class)]
class AdvancedPackageRulesTest extends TestCase
{
    public function testFilterPackage(): void
    {
        $payload = [
            'discountType' => 'discountType',
            'discountScope' => 'discountScope',
        ];

        $itemId1 = Uuid::randomHex();
        $itemId2 = Uuid::randomHex();
        $itemId3 = Uuid::randomHex();
        $itemId4 = Uuid::randomHex();

        $items = new LineItemQuantityCollection([
            new LineItemQuantity($itemId1, 1),
            new LineItemQuantity($itemId2, 2),
            new LineItemQuantity($itemId3, 1),
            new LineItemQuantity($itemId4, 4),
        ]);

        $cartItems = new LineItemFlatCollection([
            new LineItem($itemId1, 'product', $itemId1),
            new LineItem($itemId2, 'product', $itemId2),
            new LineItem($itemId3, 'product', $itemId3),
            new LineItem($itemId4, 'product', $itemId4),
        ]);

        $package = new DiscountPackage($items);
        $package->setCartItems($cartItems);

        $packages = new DiscountPackageCollection();
        $packages->add($package);
        $packages->add(new DiscountPackage(new LineItemQuantityCollection()));

        $packageRules = new AdvancedPackageRules();
        $filtered = $packageRules->filter(
            new DiscountLineItem('someLabel', new AbsolutePriceDefinition(0, new LineItemRule(identifiers: [$itemId1, $itemId3])), $payload, null),
            $packages,
            $this->createMock(SalesChannelContext::class)
        );

        $package = $packages->first();
        $filteredPackage = $filtered->first();

        static::assertNotNull($package);
        static::assertNotNull($filteredPackage);

        static::assertCount(2, $packages);
        static::assertCount(4, $package->getMetaData());
        static::assertCount(4, $package->getCartItems());

        static::assertCount(1, $filtered);
        static::assertCount(2, $filteredPackage->getMetaData());
        static::assertCount(2, $filteredPackage->getCartItems());
    }
}
