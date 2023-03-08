<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\InstallController;
use App\Services\RecoveryManager;
use App\Services\ReleaseInfoProvider;
use App\Services\StreamedCommandResponseGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 *
 * @covers \App\Controller\InstallController
 * @covers \App\Services\ProjectComposerJsonUpdater
 */
class InstallControllerTest extends TestCase
{
    public function testStartPage(): void
    {
        $recovery = $this->createMock(RecoveryManager::class);
        $recovery->method('getShopwareLocation')->willReturn('asd');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator->method('runJSON')->willReturn(new StreamedResponse());

        $controller = new InstallController($recovery, $responseGenerator, $this->createMock(ReleaseInfoProvider::class));
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->index($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('install.html.twig', $response->getContent());
    }

    public function testInstall(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('test', true);

        $recovery = $this->createMock(RecoveryManager::class);
        $recovery->method('getShopwareLocation')->willReturn('location');
        $recovery->method('getPHPBinary')->willReturn('php');
        $recovery->method('getProjectDir')->willReturn($tmpDir);

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('run')
            ->with([
                'php',
                '',
                'composer',
                'install',
                '-d',
                $tmpDir,
                '--no-interaction',
                '--no-ansi',
                '-v',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new InstallController($recovery, $responseGenerator, $this->createMock(ReleaseInfoProvider::class));
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->query->set('shopwareVersion', '6.4.10.0');

        $controller->run($request);

        (new Filesystem())->remove($tmpDir);
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
