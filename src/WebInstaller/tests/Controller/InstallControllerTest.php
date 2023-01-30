<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\InstallController;
use App\Services\RecoveryManager;
use App\Services\StreamedCommandResponseGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 *
 * @covers \App\Controller\InstallController
 */
class InstallControllerTest extends TestCase
{
    public function testStartPage(): void
    {
        $recovery = $this->createMock(RecoveryManager::class);
        $recovery->method('getShopwareLocation')->willReturn('asd');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator->method('runJSON')->willReturn(new StreamedResponse());

        $controller = new InstallController($recovery, $responseGenerator);
        $controller->setContainer($this->getContainer());

        $response = $controller->index();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('install.html.twig', $response->getContent());
    }

    public function testInstall(): void
    {
        $recovery = $this->createMock(RecoveryManager::class);
        $recovery->method('getShopwareLocation')->willReturn('location');
        $recovery->method('getPHPBinary')->willReturn('php');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('run')
            ->with([
                'php',
                '',
                'composer',
                'create-project',
                'shopware/production:dev-flex',
                '--no-interaction',
                '--no-ansi',
                '-v',
                'shopware',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new InstallController($recovery, $responseGenerator);
        $controller->setContainer($this->getContainer());

        $controller->run(new Request());
    }

    private function getContainer(): ContainerInterface
    {
        $container = new Container();

        $router = $this->createMock(Router::class);
        $router->method('generate')->willReturnArgument(0);

        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnArgument(0);

        $container->set('twig', $twig);

        return $container;
    }
}
