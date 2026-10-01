<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Capability\HeadlessSalesChannelTypeCapabilities;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(HeadlessSalesChannelTypeCapabilities::class)]
class HeadlessSalesChannelTypeCapabilitiesTest extends TestCase
{
    public function testHeadlessChannelsAreTransactional(): void
    {
        $capabilities = new HeadlessSalesChannelTypeCapabilities();

        static::assertSame(Defaults::SALES_CHANNEL_TYPE_API, $capabilities->getSalesChannelTypeId());
        static::assertTrue($capabilities->isTransactional());
    }
}
