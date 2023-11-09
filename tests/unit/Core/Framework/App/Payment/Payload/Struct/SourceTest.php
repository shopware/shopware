<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Payload\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Payment\Payload\Struct\Source;
use Shopware\Core\Framework\Log\Package;

/**
 * @covers \Shopware\Core\Framework\App\Payment\Payload\Struct\Source
 *
 * @internal
 */
#[Package('checkout')]
class SourceTest extends TestCase
{
    public function testPayload(): void
    {
        $url = 'https://foo.bar';
        $shopId = 'foo';
        $appVersion = '1.0.0';

        $source = new Source($url, $shopId, $appVersion);

        static::assertSame($url, $source->getUrl());
        static::assertSame($shopId, $source->getShopId());
        static::assertSame($appVersion, $source->getAppVersion());
    }
}
