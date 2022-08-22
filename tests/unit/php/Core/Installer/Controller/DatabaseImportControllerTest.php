<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Installer\Controller\DatabaseImportController;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Installer\Database\DatabaseMigrator;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Controller\DatabaseImportController
 */
class DatabaseImportControllerTest extends TestCase
{
    use InstallerControllerTestTrait;

    /**
     * @var DatabaseConnectionFactory&MockObject
     */
    private $connectionFactory;

    /**
     * @var DatabaseMigrator&MockObject
     */
    private $databaseMigrator;

    private DatabaseImportController $controller;

    /**
     * @var Environment&MockObject
     */
    private $twig;

    /**
     * @var RouterInterface&MockObject
     */
    private $router;

    public function setUp(): void
    {
        $this->connectionFactory = $this->createMock(DatabaseConnectionFactory::class);
        $this->databaseMigrator = $this->createMock(DatabaseMigrator::class);
        $this->twig = $this->createMock(Environment::class);
        $this->router = $this->createMock(RouterInterface::class);

        $this->controller = new DatabaseImportController(
            $this->connectionFactory,
            $this->databaseMigrator
        );
        $this->controller->setContainer($this->getInstallerContainer($this->twig, ['router' => $this->router]));
    }

    /**
     * @after
     */
    public function unsetEnvVars(): void
    {
        unset(
            $_SERVER[BlueGreenDeploymentService::ENV_NAME],
            $_ENV[BlueGreenDeploymentService::ENV_NAME],
            $_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE],
            $_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE],
        );
    }

    public function testImportDatabaseRedirectsToConfigPageWhenDatabaseConnectionWasNotConfigured(): void
    {
        $this->twig->expects(static::never())
            ->method('render');

        $this->router->expects(static::once())->method('generate')
            ->with('installer.database-configuration', [], RouterInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/database-configuration');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/installer/database-import');
        $request->setSession($session);

        $response = $this->controller->databaseImport($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/database-configuration', $response->getTargetUrl());
    }

    public function testImportDatabaseRoute(): void
    {
        $this->twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/database-import.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'supportedLanguages' => [],
                    'error' => null,
                ])
            )
            ->willReturn('import');

        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $request = Request::create('/installer/database-import');
        $request->setSession($session);

        $response = $this->controller->databaseImport($request);
        static::assertSame('import', $response->getContent());
    }

    public function testDatabaseMigrateReturnsErrorIfSessionExpired(): void
    {
        $this->databaseMigrator->expects(static::never())->method('migrate');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/installer/database-import');
        $request->setSession($session);

        $response = $this->controller->databaseMigrate($request);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertSame([
            'error' => 'Session expired, please go back to database configuration.',
        ], json_decode($response->getContent(), true));
    }

    public function testDatabaseMigrateWithoutOffset(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection);

        $result = [
            'offset' => 5,
            'total' => 10,
            'isFinished' => false,
        ];

        $this->databaseMigrator->expects(static::once())
            ->method('migrate')
            ->with(3, $connection)
            ->willReturn($result);

        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $request = Request::create('/installer/database-import', 'POST', [], [], [], [], '{"offset":3}');
        $request->setSession($session);

        $response = $this->controller->databaseMigrate($request);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertSame($result, json_decode($response->getContent(), true));
    }

    public function testDatabaseMigrateWillReportException(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->connectionFactory->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection);

        $this->databaseMigrator->expects(static::once())
            ->method('migrate')
            ->with(3, $connection)
            ->willThrowException(new \Exception('Test exception'));

        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $request = Request::create('/installer/database-import', 'POST', [], [], [], [], '{"offset":3}');
        $request->setSession($session);

        $response = $this->controller->databaseMigrate($request);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertSame([
            'error' => 'Test exception',
        ], json_decode($response->getContent(), true));
    }
}
