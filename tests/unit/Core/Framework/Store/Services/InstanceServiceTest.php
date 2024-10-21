<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InstanceService::class)]
class InstanceServiceTest extends TestCase
{
    public function testItReturnsInstanceIdIfNull(): void
    {
        $instanceService = new InstanceService(
            '6.4.0.0',
            null
        );

        static::assertNull($instanceService->getInstanceId());
    }

    public function testItReturnsInstanceIdIfSet(): void
    {
        $instanceService = new InstanceService(
            '6.4.0.0',
            'i-am-unique'
        );

        static::assertEquals('i-am-unique', $instanceService->getInstanceId());
    }

    public function testItReturnsSpecificShopwareVersion(): void
    {
        $instanceService = new InstanceService(
            '6.1.0.0',
            null
        );

        static::assertEquals('6.1.0.0', $instanceService->getShopwareVersion());
    }

    public function testItReturnsShopwareVersionStringIfVersionIsDeveloperVersion(): void
    {
        $instanceService = new InstanceService(
            Kernel::SHOPWARE_FALLBACK_VERSION,
            null
        );

        static::assertEquals('___VERSION___', $instanceService->getShopwareVersion());
    }
}
