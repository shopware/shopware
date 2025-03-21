<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;

/**
 * @internal
 */
#[CoversClass(InAppPurchase::class)]
#[Package('checkout')]
class InAppPurchaseTest extends TestCase
{
    public function testAll(): void
    {
        $iap = StaticInAppPurchaseFactory::createWithFeatures(['Extension1' => ['Purchase1', 'Purchase2'], 'Extension2' => ['Purchase2']]);

        static::assertSame(['Extension1-Purchase1', 'Extension1-Purchase2', 'Extension2-Purchase2'], $iap->formatPurchases());
    }

    public function testAllPurchases(): void
    {
        $iap = StaticInAppPurchaseFactory::createWithFeatures(['Extension1' => ['Purchase1', 'Purchase2'], 'Extension2' => ['Purchase2']]);
        static::assertSame(['Extension1' => ['Purchase1', 'Purchase2'], 'Extension2' => ['Purchase2']], $iap->all());
    }

    public function testIsActive(): void
    {
        $iap = StaticInAppPurchaseFactory::createWithFeatures(['Extension1' => ['Purchase1', 'Purchase2'], 'Extension2' => ['Purchase2']]);

        static::assertTrue($iap->isActive('Extension1', 'Purchase1'));
        static::assertTrue($iap->isActive('Extension2', 'Purchase2'));
        static::assertTrue($iap->isActive('Extension1', 'Purchase2'));
        static::assertFalse($iap->isActive('Extension1', 'inactivePurchase'));
    }

    public function testEmpty(): void
    {
        $iap = StaticInAppPurchaseFactory::createWithFeatures();

        static::assertFalse($iap->isActive('ExtensionName', 'inactivePurchase'));
        static::assertEmpty($iap->formatPurchases());
    }

    public function testRegisterPurchasesOverridesActivePurchases(): void
    {
        $iap = StaticInAppPurchaseFactory::createWithFeatures(['Extension1' => ['Purchase1', 'Purchase2'], 'Extension2' => ['Purchase2']]);

        static::assertTrue($iap->isActive('Extension1', 'Purchase1'));

        $iap = StaticInAppPurchaseFactory::createWithFeatures(['Extension1' => ['Purchase2', 'Purchase3'], 'Extension2' => ['Purchase2']]);

        static::assertFalse($iap->isActive('Extension1', 'Purchase1'));
        static::assertTrue($iap->isActive('Extension1', 'Purchase2'));
        static::assertTrue($iap->isActive('Extension1', 'Purchase3'));
    }

    public function testByExtension(): void
    {
        $iap = StaticInAppPurchaseFactory::createWithFeatures(['Extension1' => ['Purchase1', 'Purchase2'], 'Extension2' => ['Purchase2']]);

        static::assertSame(['Purchase1', 'Purchase2'], $iap->getByExtension('Extension1'));
        static::assertSame(['Purchase2'], $iap->getByExtension('Extension2'));
        static::assertEmpty($iap->getByExtension('Extension3'));
    }
}
