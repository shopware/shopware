<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Controller\DatabaseConfigurationController;
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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Controller\DatabaseConfigurationController
 * @covers \Shopware\Core\Installer\Controller\InstallerController
 */
class DatabaseConfigurationControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    /**
     * @var Environment&MockObject
     */
    private $twig;

    /**
     * @var TranslatorInterface&MockObject
     */
    private $translator;

    /**
     * @var BlueGreenDeploymentService&MockObject
     */
    private $blueGreenDeploymentService;

    /**
     * @var JwtCertificateGenerator&MockObject
     */
    private $jwtCertificateGenerator;

    /**
     * @var SetupDatabaseAdapter&MockObject
     */
    private $setupDatabaseAdapter;

    /**
     * @var DatabaseConnectionFactory&MockObject
     */
    private $connectionFactory;

    /**
     * @var RouterInterface&MockObject
     */
    private $router;

    private DatabaseConfigurationController $controller;

    public function setUp(): void
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
            ->with('installer.database-import', [], RouterInterface::ABSOLUTE_PATH)
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
            ->withConsecutive(
                [static::isInstanceOf(DatabaseConnectionInformation::class)],
                [static::isInstanceOf(DatabaseConnectionInformation::class), true],
                [static::isInstanceOf(DatabaseConnectionInformation::class)],
            )
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
            ->with('installer.database-import', [], RouterInterface::ABSOLUTE_PATH)
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
            ->with('shopware.installer.database-configuration_error_required_fields')
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
            ->willThrowException(new \Exception());

        $this->setupDatabaseAdapter->expects(static::never())->method('getExistingDatabases');
        $this->setupDatabaseAdapter->expects(static::never())->method('getTableCount');

        $response = $this->controller->databaseInformation($request);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{}', $response->getContent());
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
            ->withConsecutive(
                [$connection, 'empty-db'],
                [$connection, 'used-db']
            )->willReturnOnConsecutiveCalls(0, 4);

        $response = $this->controller->databaseInformation($request);
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            'empty-db' => false,
            'used-db' => true,
        ], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
