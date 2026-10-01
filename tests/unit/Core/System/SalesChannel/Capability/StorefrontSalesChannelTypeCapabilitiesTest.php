<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Capability\StorefrontSalesChannelTypeCapabilities;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontSalesChannelTypeCapabilities::class)]
class StorefrontSalesChannelTypeCapabilitiesTest extends TestCase
{
    public function testStorefrontChannelsAreTransactional(): void
    {
        $capabilities = new StorefrontSalesChannelTypeCapabilities();

        static::assertSame(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, $capabilities->getSalesChannelTypeId());
        static::assertTrue($capabilities->isTransactional());
    }
}
