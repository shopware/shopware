<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Payload\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Source::class)]
class SourceTest extends TestCase
{
    public function testPayload(): void
    {
        $url = 'https://foo.bar';
        $shopId = 'foo';
        $appVersion = '1.0.0';
        $inAppPurchases = ['purchase-1', 'purchase-2'];

        $source = new Source($url, $shopId, $appVersion, $inAppPurchases);

        static::assertSame($url, $source->getUrl());
        static::assertSame($shopId, $source->getShopId());
        static::assertSame($appVersion, $source->getAppVersion());
        static::assertSame($inAppPurchases, $source->getInAppPurchases());
    }
}
