<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Capability\AbstractSalesChannelTypeCapabilities;
use Shopware\Core\System\SalesChannel\Capability\HeadlessSalesChannelTypeCapabilities;
use Shopware\Core\System\SalesChannel\Capability\SalesChannelTypeCapabilityRegistry;
use Shopware\Core\System\SalesChannel\Capability\StorefrontSalesChannelTypeCapabilities;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTypeCapabilityRegistry::class)]
class SalesChannelTypeCapabilityRegistryTest extends TestCase
{
    public function testListsOnlyTypesThatAreTransactional(): void
    {
        $nonTransactionalCapabilities = new class extends AbstractSalesChannelTypeCapabilities {
            public function getSalesChannelTypeId(): string
            {
                return Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON;
            }
        };

        $registry = new SalesChannelTypeCapabilityRegistry([
            new StorefrontSalesChannelTypeCapabilities(),
            $nonTransactionalCapabilities,
            new HeadlessSalesChannelTypeCapabilities(),
        ]);

        static::assertSame(
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, Defaults::SALES_CHANNEL_TYPE_API],
            $registry->getTransactionalTypeIds()
        );
    }

    public function testListsNoTypeWhenNothingIsRegistered(): void
    {
        $registry = new SalesChannelTypeCapabilityRegistry([]);

        static::assertSame([], $registry->getTransactionalTypeIds());
    }
}
