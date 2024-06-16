<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;

/**
 * @internal
 */
#[CoversClass(InAppPurchase::class)]
#[Package('checkout')]
class InAppPurchaseTest extends TestCase
{
    protected function tearDown(): void
    {
        InAppPurchase::reset();
    }

    public function testAll(): void
    {
        InAppPurchase::registerPurchases(['purchase1' => 'extension-1', 'purchase2' => 'extension-2']);

        static::assertSame(['purchase1', 'purchase2'], InAppPurchase::all());
    }

    public function testAllPurchases(): void
    {
        InAppPurchase::registerPurchases(['purchase1' => 'extension-1', 'purchase2' => 'extension-2']);
        static::assertSame(['purchase1' => 'extension-1', 'purchase2' => 'extension-2'], InAppPurchase::allPurchases());
    }

    public function testIsActive(): void
    {
        static::assertFalse(InAppPurchase::isActive('activePurchase'));

        InAppPurchase::registerPurchases(['activePurchase' => 'extension-1']);

        static::assertTrue(InAppPurchase::isActive('activePurchase'));
        static::assertFalse(InAppPurchase::isActive('inactivePurchase'));
    }

    public function testEmpty(): void
    {
        InAppPurchase::registerPurchases([]);

        static::assertFalse(InAppPurchase::isActive('inactivePurchase'));
        static::assertEmpty(InAppPurchase::all());
    }

    public function testRegisterPurchasesOverridesActivePurchases(): void
    {
        InAppPurchase::registerPurchases(['purchase1' => 'extension-1']);

        static::assertTrue(InAppPurchase::isActive('purchase1'));

        InAppPurchase::registerPurchases(['purchase2' => 'extension-1']);

        static::assertFalse(InAppPurchase::isActive('purchase1'));
        static::assertTrue(InAppPurchase::isActive('purchase2'));
    }

    public function testReset(): void
    {
        InAppPurchase::registerPurchases(['purchase1' => 'extension-1']);

        static::assertTrue(InAppPurchase::isActive('purchase1'));
        static::assertSame(['purchase1'], InAppPurchase::all());

        InAppPurchase::reset();

        static::assertFalse(InAppPurchase::isActive('purchase1'));
    }

    public function testByExtension(): void
    {
        InAppPurchase::registerPurchases(['purchase1' => 'extension-1', 'purchase2' => 'extension-2']);

        static::assertSame(['purchase1'], InAppPurchase::getByExtension('extension-1'));
        static::assertSame(['purchase2'], InAppPurchase::getByExtension('extension-2'));
        static::assertEmpty(InAppPurchase::getByExtension('extension-3'));
    }
}
