<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Deployment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AirGappedMode::class)]
class AirGappedModeTest extends TestCase
{
    public function testIsEnabled(): void
    {
        static::assertFalse((new AirGappedMode(false))->isEnabled());
        static::assertTrue((new AirGappedMode(true))->isEnabled());
    }

    public function testDenyShopwareOperatedHttpAllowsWhenDisabled(): void
    {
        $mode = new AirGappedMode(false);

        $mode->denyShopwareOperatedHttp();

        static::assertFalse($mode->isEnabled());
    }

    public function testDenyShopwareOperatedHttpThrowsWhenEnabled(): void
    {
        $mode = new AirGappedMode(true);

        $this->expectExceptionObject(FrameworkException::airGapped());
        $mode->denyShopwareOperatedHttp();
    }
}
