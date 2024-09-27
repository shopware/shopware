<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\InAppPurchaseSyncTask;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseSyncTask::class)]
class InAppPurchaseSyncTaskTest extends TestCase
{
    public static function testGetTaskName(): void
    {
        static::assertSame('in-app-purchase.fetch.active', InAppPurchaseSyncTask::getTaskName());
    }

    public static function testGetDefaultInterval(): void
    {
        static::assertEquals(60 * 60 * 24, InAppPurchaseSyncTask::getDefaultInterval());
    }
}
