<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\HealthCheckController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\Controller\HealthCheckController
 */
class HealthCheckControllerTest extends TestCase
{
    public function testCheck(): void
    {
        $controller = new HealthCheckController();
        $response = $controller->check();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertFalse($response->isCacheable());
    }
}
