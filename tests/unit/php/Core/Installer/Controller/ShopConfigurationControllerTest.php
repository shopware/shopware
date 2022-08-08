<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Installer\Configuration\AdminConfigurationService;
use Shopware\Core\Installer\Configuration\EnvConfigWriter;
use Shopware\Core\Installer\Configuration\ShopConfigurationService;
use Shopware\Core\Installer\Controller\ShopConfigurationController;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Controller\ShopConfigurationController
 */
class ShopConfigurationControllerTest extends TestCase
{
    use InstallerControllerTestTrait;
    use EnvTestBehaviour;

    /**
     * @var Environment&MockObject
     */
    private $twig;

    /**
     * @var RouterInterface&MockObject
     */
    private $router;

    /**
     * @var Connection&MockObject
     */
    private $connection;

    /**
     * @var EnvConfigWriter&MockObject
     */
    private $envConfigWriter;

    /**
     * @var ShopConfigurationService&MockObject
     */
    private $shopConfigService;

    /**
     * @var AdminConfigurationService&MockObject
     */
    private $adminConfigService;

    private ShopConfigurationController $controller;

    public function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->router = $this->createMock(RouterInterface::class);

        $this->connection = $this->createMock(Connection::class);
        $connectionFactory = $this->createMock(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($this->connection);

        $this->envConfigWriter = $this->createMock(EnvConfigWriter::class);
        $this->shopConfigService = $this->createMock(ShopConfigurationService::class);
        $this->adminConfigService = $this->createMock(AdminConfigurationService::class);

        $this->controller = new ShopConfigurationController(
            $connectionFactory,
            $this->envConfigWriter,
            $this->shopConfigService,
            $this->adminConfigService,
            ['de' => 'de-DE', 'en' => 'en-GB'],
            ['EUR', 'USD']
        );
        $this->controller->setContainer($this->getInstallerContainer($this->twig, ['router' => $this->router]));
    }

    public function testGetConfigurationRoute(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setMethod('GET');
        $request->setSession($session);
        $request->attributes->set('_locale', 'de');

        $this->connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['iso3' => 'DEU', 'iso' => 'DE'],
                ['iso3' => 'GBR', 'iso' => 'GB'],
            ]);

        $this->twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/shop-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'error' => null,
                    'countryIsos' => [['iso3' => 'DEU', 'default' => true], ['iso3' => 'GBR', 'default' => false]],
                    'currencyIsos' => ['EUR', 'USD'],
                    'languageIsos' => ['de' => 'de-DE', 'en' => 'en-GB'],
                    'parameters' => ['config_shop_language' => 'de-DE'],
                ])
            )
            ->willReturn('config');

        $response = $this->controller->shopConfiguration($request);
        static::assertSame('config', $response->getContent());
    }

    public function testGetConfigurationRouteRedirectsIfSessionIsExpired(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setMethod('GET');
        $request->setSession($session);

        $this->router->expects(static::once())->method('generate')
            ->with('installer.database-configuration', [], RouterInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/database-configuration');

        $this->twig->expects(static::never())->method('render');

        $response = $this->controller->shopConfiguration($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/database-configuration', $response->getTargetUrl());
    }

    public function testPostConfigurationRoute(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setMethod('POST');
        $connectionInfo = new DatabaseConnectionInformation();
        $session->set(DatabaseConnectionInformation::class, $connectionInfo);
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setSession($session);
        $request->attributes->set('_locale', 'de');

        $request->request->set('config_admin_email', 'test@test.com');
        $request->request->set('config_admin_username', 'admin');
        $request->request->set('config_admin_firstName', 'first');
        $request->request->set('config_admin_lastName', 'last');
        $request->request->set('config_admin_password', 'shopware');

        $request->request->set('config_shop_language', 'de-DE');
        $request->request->set('config_shop_currency', 'EUR');
        $request->request->set('config_shop_country', 'DEU');
        $request->request->set('config_shopName', 'shop');
        $request->request->set('config_mail', 'info@test.com');
        $request->request->set('available_currencies', ['EUR', 'USD']);

        $this->setEnvVars([
            'HTTPS' => true,
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        $expectedShopInfo = [
            'name' => 'shop',
            'locale' => 'de-DE',
            'currency' => 'EUR',
            'additionalCurrencies' => ['EUR', 'USD'],
            'country' => 'DEU',
            'email' => 'info@test.com',
            'host' => 'localhost',
            'https' => true,
            'basePath' => '/shop',
            'blueGreenDeployment' => true,
        ];

        $this->envConfigWriter->expects(static::once())->method('writeConfig')->with($connectionInfo, $expectedShopInfo);
        $this->shopConfigService->expects(static::once())->method('updateShop')->with($expectedShopInfo, $this->connection);

        $this->adminConfigService->expects(static::once())->method('createAdmin')->with([
            'email' => 'test@test.com',
            'username' => 'admin',
            'firstName' => 'first',
            'lastName' => 'last',
            'password' => 'shopware',
            'locale' => 'de-DE',
        ], $this->connection);

        $this->router->expects(static::once())->method('generate')
            ->with('installer.shop-configuration', [], RouterInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/shop-configuration');

        $this->twig->expects(static::never())->method('render');

        $response = $this->controller->shopConfiguration($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/shop-configuration', $response->getTargetUrl());
    }

    public function testPostConfigurationRouteOnError(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setMethod('POST');
        $request->setSession($session);
        $request->attributes->set('_locale', 'de');

        $this->setEnvVars([
            'HTTPS' => true,
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        $this->connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['iso3' => 'DEU', 'iso' => 'DE'],
                ['iso3' => 'GBR', 'iso' => 'GB'],
            ]);

        $this->envConfigWriter->expects(static::once())->method('writeConfig')->willThrowException(new \Exception('Test Exception'));

        $this->twig->expects(static::once())->method('render')
            ->with(
                '@Installer/installer/shop-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'error' => 'Test Exception',
                    'countryIsos' => [['iso3' => 'DEU', 'default' => true], ['iso3' => 'GBR', 'default' => false]],
                    'currencyIsos' => ['EUR', 'USD'],
                    'languageIsos' => ['de' => 'de-DE', 'en' => 'en-GB'],
                    'parameters' => ['config_shop_language' => 'de-DE'],
                ])
            )
            ->willReturn('config');

        $response = $this->controller->shopConfiguration($request);
        static::assertSame('config', $response->getContent());
    }
}
