<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;

/**
 * @internal
 */
class HealthCheckControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testHealthyInstance(): void
    {
        $this->getBrowser()->request('GET', '/api/_info/health-check');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());
    }

    public function testHealthCheckEventIsDispatched(): void
    {
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->addEventListener($this->getContainer()->get('event_dispatcher'), HealthCheckEvent::class, $listener);

        $this->getBrowser()->request('GET', '/api/_info/health-check');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());
    }

    public function testUnhealthyInstance(): void
    {
        $failingHealthCheckListener = function (): void {
            throw new \Exception('test-exception');
        };
        $this->addEventListener($this->getContainer()->get('event_dispatcher'), HealthCheckEvent::class, $failingHealthCheckListener);

        $this->getBrowser()->request('GET', '/api/_info/health-check');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(500, $response->getStatusCode());
    }
}
