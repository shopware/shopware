<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Capability\AbstractSalesChannelTypeCapabilities;
use Shopware\Core\System\SalesChannel\Capability\SalesChannelTypeCapabilityRegistry;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTypeCapabilityRegistry::class)]
class SalesChannelTypeCapabilityRegistryTest extends TestCase
{
    public function testListsTheTransactionalCoreTypesWithoutRegistrations(): void
    {
        $registry = new SalesChannelTypeCapabilityRegistry([]);

        static::assertSame(
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, Defaults::SALES_CHANNEL_TYPE_API],
            $registry->getTransactionalTypeIds()
        );
    }

    public function testAddsRegisteredTransactionalTypesOnce(): void
    {
        $pluginTypeId = Uuid::randomHex();

        $registry = new SalesChannelTypeCapabilityRegistry([
            $this->createCapabilities($pluginTypeId, true),
            $this->createCapabilities(Uuid::randomHex(), false),
            $this->createCapabilities(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, true),
        ]);

        static::assertSame(
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, Defaults::SALES_CHANNEL_TYPE_API, $pluginTypeId],
            $registry->getTransactionalTypeIds()
        );
    }

    public function testARegistrationCannotTakeTransactionalAwayFromACoreType(): void
    {
        $registry = new SalesChannelTypeCapabilityRegistry([
            $this->createCapabilities(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, false),
        ]);

        static::assertTrue($registry->isTransactional(Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
    }

    public function testUnknownAndFeedTypesAreNotTransactional(): void
    {
        $registry = new SalesChannelTypeCapabilityRegistry([]);

        static::assertFalse($registry->isTransactional(Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON));
        static::assertFalse($registry->isTransactional(Uuid::randomHex()));
    }

    private function createCapabilities(string $salesChannelTypeId, bool $isDeclaredTransactional): AbstractSalesChannelTypeCapabilities
    {
        return new class($salesChannelTypeId, $isDeclaredTransactional) extends AbstractSalesChannelTypeCapabilities {
            public function __construct(
                private readonly string $salesChannelTypeId,
                private readonly bool $isDeclaredTransactional,
            ) {
            }

            public function getSalesChannelTypeId(): string
            {
                return $this->salesChannelTypeId;
            }

            public function isTransactional(): bool
            {
                return $this->isDeclaredTransactional;
            }
        };
    }
}
