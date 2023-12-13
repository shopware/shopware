<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AppController::class)]
class AppControllerTest extends TestCase
{
    public function testGenerate(): void
    {
        $appJWTGenerateRoute = $this->createMock(AppJWTGenerateRoute::class);
        $appJWTGenerateRoute->expects(static::once())->method('generate')->with('test');

        $controller = new AppController($appJWTGenerateRoute);
        $controller->generateToken('test', Generator::createSalesChannelContext());
    }

    public function testGenerateFails(): void
    {
        $appJWTGenerateRoute = $this->createMock(AppJWTGenerateRoute::class);
        $appJWTGenerateRoute->expects(static::once())->method('generate')->willThrowException(AppException::jwtGenerationRequiresCustomerLoggedIn());

        $controller = new AppController($appJWTGenerateRoute);
        $response = $controller->generateToken('test', Generator::createSalesChannelContext());
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('message', $data);
        static::assertSame('JWT generation requires customer to be logged in', $data['message']);
    }
}
