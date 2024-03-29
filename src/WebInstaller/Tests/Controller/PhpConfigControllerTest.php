<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\WebInstaller\Controller\PhpConfigController;
use Shopware\WebInstaller\Services\PhpBinaryFinder;
use Shopware\WebInstaller\Services\RecoveryManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(PhpConfigController::class)]
class PhpConfigControllerTest extends TestCase
{
    public function testIndex(): void
    {
        $controller = new PhpConfigController($this->createMock(PhpBinaryFinder::class), $this->createMock(RecoveryManager::class));
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->index($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSetConfigOnUpdate(): void
    {
        $controller = new PhpConfigController($this->createMock(PhpBinaryFinder::class), $this->createMock(RecoveryManager::class));
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->request->set('phpBinary', 'php-test');

        $response = $controller->index($request);

        static::assertSame('php-test', $request->getSession()->get('phpBinary'));

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('update', $response->headers->get('Location'));
    }

    public function testSetConfigOnInstall(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);
        $recoveryManager->method('getShopwareLocation')->willThrowException(new \RuntimeException('cannot find shopware'));

        $controller = new PhpConfigController($this->createMock(PhpBinaryFinder::class), $recoveryManager);
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->request->set('phpBinary', 'php-test');

        $response = $controller->index($request);

        static::assertSame('php-test', $request->getSession()->get('phpBinary'));

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('install', $response->headers->get('Location'));
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
