<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Increment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class IncrementerGatewayRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGet(): void
    {
        $registry = $this->getContainer()->get('shopware.increment.gateway.registry');

        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::USER_ACTIVITY_POOL));
        static::assertInstanceOf(AbstractIncrementer::class, $registry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL));
    }

    public function testGetWithInvalidPool(): void
    {
        static::expectException(IncrementGatewayNotFoundException::class);
        static::expectExceptionMessage('Increment gateway for pool "custom_pool" was not found.');

        $registry = $this->getContainer()->get('shopware.increment.gateway.registry');
        static::assertNull($registry->get('custom_pool'));
    }
}
