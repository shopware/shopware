<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\InAppPurchase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Test\PHPUnit\Extension\InAppPurchase\InAppPurchaseExtension;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseExtension::class)]
class InAppPurchaseExtensionTest extends TestCase
{
    public function testInAppPurchaseExtension(): void
    {
        static::assertEmpty(InAppPurchase::all());

        // does not clean up after itself
        InAppPurchase::registerPurchases([
            'test' => 'test',
        ]);
    }

    #[Depends('testInAppPurchaseExtension')]
    public function testInAppPurchaseExtension2(): void
    {
        // awaits a cleaned up state after the previous test
        static::assertEmpty(InAppPurchase::all(), 'InAppPurchases are not cleaned up after test execution. Check ' . InAppPurchaseExtension::class);
    }
}
