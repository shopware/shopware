<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\DatabaseConfigurationController;
use Shopware\Core\Installer\Controller\InstallerController;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Maintenance\System\Exception\DatabaseSetupException;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(DatabaseConfigurationController::class)]
#[CoversClass(InstallerController::class)]
class DatabaseConfigurationControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    private MockObject&Environment $twig;

    private MockObject&TranslatorInterface $translator;

    private MockObject&BlueGreenDeploymentService $blueGreenDeploymentService;

    private MockObject&JwtCertificateGenerator $jwtCertificateGenerator;

    private MockObject&SetupDatabaseAdapter $setupDatabaseAdapter;

    private MockObject&DatabaseConnectionFactory $connectionFactory;

    private MockObject&RouterInterface $router;

    private DatabaseConfigurationController $controller;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->blueGreenDeploymentService = $this->createMock(BlueGreenDeploymentService::class);
        $this->jwtCertificateGenerator = $this->createMock(JwtCertificateGenerator::class);
        $this->setupDatabaseAdapter = $this->createMock(SetupDatabaseAdapter::class);
        $this->connectionFactory = $this->createMock(DatabaseConnectionFactory::class);
        $this->router = $this->createMock(RouterInterface::class);

        $this->controller = new DatabaseConfigurationController(
            $this->translator,
            $this->blueGreenDeploymentService,
            $this->jwtCertificateGenerator,
            $this->setupDatabaseAdapter,
            $this->connectionFactory,
            __DIR__
        );
        $this->controller->setContainer($this->getInstallerContainer($this->twig, ['router' => $this->router]));
    }

    public function testDatabaseGetConfigurationRoute(): void
    {
        $this->twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/database-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'connectionInfo' => new DatabaseConnectionInformation(),
                    'error' => null,
                ])
            )
            ->willReturn('config');

        $this->connectionFactory->expects(static::never())->method('getConnection');

        $request = Request::create('/installer/database-configuration');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $this->controller->databaseConfiguration($request);
        static::assertSame('config', $response->getContent());

        static::assertFalse($session->has(DatabaseConnectionInformation::class));
    }

    public function testDatabaseGetConfigurationRoutePostWithEmptyExistingDB(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection);

        $this->blueGreenDeploymentService->expects(static::once())
            ->method('setEnvironmentVariable')
            ->with($connection);

        $this->setupDatabaseAdapter->expects(static::once())
            ->method('getTableCount')
            ->with($connection, 'test')
            ->willReturn(0);

        $this->jwtCertificateGenerator->expects(static::once())
            ->method('generate')
            ->with(__DIR__ . '/config/jwt/private.pem', __DIR__ . '/config/jwt/public.pem');

        $this->twig->expects(static::never())->method('render');

        $this->router->expects(static::once())->method('generate')
            ->with('installer.database-import', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/database-import');

        $request = Request::create('/installer/database-configuration', 'POST', ['databaseName' => 'test']);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $this->controller->databaseConfiguration($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/database-import', $response->getTargetUrl());

        static::assertTrue($session->has(DatabaseConnectionInformation::class));
    }

    public function testDatabaseGetConfigurationRoutePostWithNonEmptyExistingDB(): void
    {
        $this->twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/database-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'connectionInfo' => new DatabaseConnectionInformation(),
                    'error' => 'translated error',
                ])
            )
            ->willReturn('config');

        $this->translator->expects(static::once())
            ->method('trans')
            ->with('shopware.installer.database-configuration_non_empty_database')
            ->willReturn('translated error');

        $connection = $this->createMock(Connection::class);

        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection);

        $this->blueGreenDeploymentService->expects(static::once())
            ->method('setEnvironmentVariable')
            ->with($connection);

        $this->setupDatabaseAdapter->expects(static::once())
            ->method('getTableCount')
            ->with($connection, 'test')
            ->willReturn(12);

        $this->jwtCertificateGenerator->expects(static::never())
            ->method('generate');

        $request = Request::create('/installer/database-configuration', 'POST', ['databaseName' => 'test']);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $this->controller->databaseConfiguration($request);
        static::assertSame('config', $response->getContent());

        static::assertTrue($session->has(DatabaseConnectionInformation::class));
    }

    public function testDatabaseGetConfigurationRoutePostWithNonExistingDB(): void
    {
        $connectionWithoutDb = $this->createMock(Connection::class);
        $connection = $this->createMock(Connection::class);

        $this->connectionFactory->expects(static::exactly(3))
            ->method('getConnection')
            ->willReturnOnConsecutiveCalls(
                static::throwException(new DummyDoctrineException(1049)),
                $connectionWithoutDb,
                $connection
            );

        $this->blueGreenDeploymentService->expects(static::once())
            ->method('setEnvironmentVariable')
            ->with($connection);

        $this->setupDatabaseAdapter->expects(static::once())
            ->method('createDatabase')
            ->with($connectionWithoutDb, 'test');

        $this->setupDatabaseAdapter->expects(static::once())
            ->method('getTableCount')
            ->with($connection)
            ->willReturn(0);

        $this->jwtCertificateGenerator->expects(static::once())
            ->method('generate')
            ->with(__DIR__ . '/config/jwt/private.pem', __DIR__ . '/config/jwt/public.pem');

        $this->twig->expects(static::never())->method('render');

        $this->router->expects(static::once())->method('generate')
            ->with('installer.database-import', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/database-import');

        $request = Request::create('/installer/database-configuration', 'POST', ['databaseName' => 'test']);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $this->controller->databaseConfiguration($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/database-import', $response->getTargetUrl());

        static::assertTrue($session->has(DatabaseConnectionInformation::class));
    }

    public function testDatabaseGetConfigurationRoutePostWithUnexpectedException(): void
    {
        $this->twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/database-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'connectionInfo' => new DatabaseConnectionInformation(),
                    'error' => 'Driver error',
                ])
            )
            ->willReturn('config');

        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willThrowException(new DummyDoctrineException(9999, 'Driver error'));

        $this->blueGreenDeploymentService->expects(static::never())
            ->method('setEnvironmentVariable');

        $this->setupDatabaseAdapter->expects(static::never())
            ->method('createDatabase');

        $this->jwtCertificateGenerator->expects(static::never())
            ->method('generate');

        $request = Request::create('/installer/database-configuration', 'POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $this->controller->databaseConfiguration($request);
        static::assertSame('config', $response->getContent());

        static::assertFalse($session->has(DatabaseConnectionInformation::class));
    }

    public function testDatabaseGetConfigurationRoutePostWithDatabaseSetupException(): void
    {
        $this->twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/database-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'connectionInfo' => new DatabaseConnectionInformation(),
                    'error' => 'translated error',
                ])
            )
            ->willReturn('config');

        $this->translator->expects(static::once())
            ->method('trans')
            ->with('shopware.installer.database-configuration_invalid_requirements')
            ->willReturn('translated error');

        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willThrowException(new DatabaseSetupException(''));

        $this->blueGreenDeploymentService->expects(static::never())
            ->method('setEnvironmentVariable');

        $this->setupDatabaseAdapter->expects(static::never())
            ->method('createDatabase');

        $this->jwtCertificateGenerator->expects(static::never())
            ->method('generate');

        $request = Request::create('/installer/database-configuration', 'POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $this->controller->databaseConfiguration($request);
        static::assertSame('config', $response->getContent());

        static::assertFalse($session->has(DatabaseConnectionInformation::class));
    }

    public function testDatabaseInformationRouteWithIncompleteConnectionInformation(): void
    {
        $request = Request::create('/installer/database-information', 'POST');

        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willThrowException(new \Exception('some error'));

        $this->setupDatabaseAdapter->expects(static::never())->method('getExistingDatabases');
        $this->setupDatabaseAdapter->expects(static::never())->method('getTableCount');

        $response = $this->controller->databaseInformation($request);
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame('{"error":"some error"}', $response->getContent());
    }

    public function testDatabaseInformationRouteWithWrongMysqlVersion(): void
    {
        $request = Request::create('/installer/database-information', 'POST');

        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willThrowException(new DatabaseSetupException());

        $this->translator->expects(static::once())
            ->method('trans')
            ->with('shopware.installer.database-configuration_invalid_requirements')
            ->willReturn('translated error');

        $this->setupDatabaseAdapter->expects(static::never())->method('getExistingDatabases');
        $this->setupDatabaseAdapter->expects(static::never())->method('getTableCount');

        $response = $this->controller->databaseInformation($request);
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame('{"error":"translated error"}', $response->getContent());
    }

    public function testDatabaseInformationRoute(): void
    {
        $request = Request::create('/installer/database-information', 'POST');

        $connection = $this->createMock(Connection::class);

        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection);

        $this->setupDatabaseAdapter->expects(static::once())
            ->method('getExistingDatabases')
            ->with($connection, ['information_schema', 'performance_schema', 'sys', 'mysql'])
            ->willReturn(['empty-db', 'used-db']);

        $this->setupDatabaseAdapter->expects(static::exactly(2))
            ->method('getTableCount')
            ->willReturnOnConsecutiveCalls(0, 4);

        $response = $this->controller->databaseInformation($request);
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            'empty-db' => false,
            'used-db' => true,
        ], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
