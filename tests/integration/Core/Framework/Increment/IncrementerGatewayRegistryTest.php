<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Increment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class IncrementerGatewayRegistryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetUserActivityPool(): void
    {
        $registry = static::getContainer()->get('shopware.increment.gateway.registry');

        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::USER_ACTIVITY_POOL));
    }

    /**
     * @deprecated tag:v6.8.0 - Test will be removed
     */
    public function testGetMessageQueuePool(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $registry = static::getContainer()->get('shopware.increment.gateway.registry');

        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL));
    }

    public function testGetWithInvalidPool(): void
    {
        static::expectException(IncrementGatewayNotFoundException::class);
        static::expectExceptionMessage('Increment gateway for pool "custom_pool" was not found.');

        $registry = static::getContainer()->get('shopware.increment.gateway.registry');
        static::assertNull($registry->get('custom_pool'));
    }
}
