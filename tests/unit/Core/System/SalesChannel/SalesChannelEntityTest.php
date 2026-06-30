<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelEntity::class)]
class SalesChannelEntityTest extends TestCase
{
    public function testGetSetMaintenanceIpAllowlist(): void
    {
        $entity = new SalesChannelEntity();
        static::assertNull($entity->getMaintenanceIpAllowlist());

        $entity->setMaintenanceIpAllowlist(['127.0.0.1', '::1']);

        static::assertSame(['127.0.0.1', '::1'], $entity->getMaintenanceIpAllowlist());
    }

    public function testDeprecatedGetterReturnsAllowlistValue(): void
    {
        $entity = new SalesChannelEntity();
        $entity->setMaintenanceIpAllowlist(['192.168.0.1']);

        $result = Feature::silent('v6.8.0.0', fn (): ?array => $entity->getMaintenanceIpWhitelist());

        static::assertSame(['192.168.0.1'], $result);
    }

    public function testDeprecatedSetterUpdatesAllowlist(): void
    {
        $entity = new SalesChannelEntity();

        Feature::silent('v6.8.0.0', function () use ($entity): void {
            $entity->setMaintenanceIpWhitelist(['10.0.0.1']);
        });

        static::assertSame(['10.0.0.1'], $entity->getMaintenanceIpAllowlist());
    }

    public function testDeprecatedGetterThrowsWhenMajorIsActive(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('The deprecation only throws while the v6.8.0.0 feature flag is active.');
        }

        $this->expectException(\Throwable::class);

        (new SalesChannelEntity())->getMaintenanceIpWhitelist();
    }
}
