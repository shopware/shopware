<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Controller\IndexController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(IndexController::class)]
class IndexControllerTest extends TestCase
{
    public function testIndexRedirectsToInstall(): void
    {
        $router = $this->createMock(Router::class);
        $router
            ->method('generate')
            ->willReturnArgument(0);

        $controller = new IndexController();
        $container = new Container();
        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnArgument(0);

        $container->set('twig', $twig);

        $controller->setContainer($container);

        $response = $controller->index();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('index.html.twig', $response->getContent());
    }
}
