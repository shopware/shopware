<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\InAppPurchaseUpdateTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseUpdateTask::class)]
class InAppPurchaseUpdateTaskTest extends TestCase
{
    public function testMeta(): void
    {
        static::assertSame('in-app-purchase.update', InAppPurchaseUpdateTask::getTaskName());
        static::assertSame(86400, InAppPurchaseUpdateTask::getDefaultInterval());
        static::assertTrue(InAppPurchaseUpdateTask::shouldRescheduleOnFailure());
        static::assertTrue(InAppPurchaseUpdateTask::shouldRun(new ParameterBag()));
        static::assertTrue(InAppPurchaseUpdateTask::shouldRun(new ParameterBag([
            'shopware.deployment.air_gapped' => false,
        ])));
    }

    public function testShouldNotRunWhenAirGapped(): void
    {
        static::assertFalse(InAppPurchaseUpdateTask::shouldRun(new ParameterBag([
            'shopware.deployment.air_gapped' => true,
        ])));
    }
}
