<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(ShopConfigurationController::class)]
class ShopConfigurationControllerTest extends TestCase
{
    use EnvTestBehaviour;
    use InstallerControllerTestTrait;

    private MockObject&Environment $twig;

    private MockObject&RouterInterface $router;

    private Connection&MockObject $connection;

    private MockObject&EnvConfigWriter $envConfigWriter;

    private MockObject&ShopConfigurationService $shopConfigService;

    private MockObject&AdminConfigurationService $adminConfigService;

    private TranslationConfig $translationConfig;

    private ShopConfigurationController $controller;

    /**
     * @var TranslatorInterface&MockObject
     */
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->router = $this->createMock(RouterInterface::class);

        $this->connection = $this->createMock(Connection::class);
        $connectionFactory = $this->createMock(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($this->connection);

        $this->envConfigWriter = $this->createMock(EnvConfigWriter::class);
        $this->shopConfigService = $this->createMock(ShopConfigurationService::class);
        $this->adminConfigService = $this->createMock(AdminConfigurationService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->translationConfig = new TranslationConfig(
            new Uri('http://localhost:8000'),
            [],
            [],
            new LanguageCollection([
                new Language('en-US', 'English (US)'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            []
        );
        $this->controller = new ShopConfigurationController(
            $connectionFactory,
            $this->envConfigWriter,
            $this->shopConfigService,
            $this->adminConfigService,
            $this->translator,
            $this->translationConfig,
            [
                'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
                'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
            ],
            ['EUR', 'USD', 'GBP']
        );
        $this->controller->setContainer($this->getInstallerContainer($this->twig, ['router' => $this->router]));
    }

    #[DataProvider('shopConfigurationPresetProvider')]
    public function testGetConfigurationRoute(
        string $requestLocale,
        string $expectedShopLanguage,
        string $expectedPresetCurrency,
        string $expectedCountryIsoDefault
    ): void {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setMethod('GET');
        $request->setSession($session);
        $request->attributes->set('_locale', $requestLocale);

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['iso3' => 'DEU', 'iso' => 'DE'],
                ['iso3' => 'GBR', 'iso' => 'GB'],
                ['iso3' => 'USA', 'iso' => 'US'],
            ]);

        $this->translator->method('trans')->willReturnCallback(
            function (string $key): string {
                return $this->getLanguageTranslations()[$key] ?? $key;
            }
        );

        $this->twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/shop-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'error' => null,
                    'countryIsos' => [
                        ['iso3' => 'DEU', 'default' => $expectedCountryIsoDefault === 'DEU', 'translated' => 'shopware.installer.select_country_deu'],
                        ['iso3' => 'GBR', 'default' => $expectedCountryIsoDefault === 'GBR', 'translated' => 'shopware.installer.select_country_gbr'],
                        ['iso3' => 'USA', 'default' => $expectedCountryIsoDefault === 'USA', 'translated' => 'shopware.installer.select_country_usa'],
                    ],
                    'currencyIsos' => ['EUR', 'USD', 'GBP'],
                    'languageIsos' => [
                        'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                        'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
                    ],
                    'allAvailableLanguages' => [
                        'de-DE' => ['id' => 'de-DE', 'label' => 'Deutsch'],
                        'en-GB' => ['id' => 'en-GB', 'label' => 'English'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                    ],
                    'parameters' => [
                        'config_shop_language' => $expectedShopLanguage,
                        'config_shop_currency' => $expectedPresetCurrency,
                    ],
                    'selectedLanguages' => [],
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

        $this->router->expects($this->once())->method('generate')
            ->with('installer.database-configuration', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/database-configuration');

        $this->twig->expects($this->never())->method('render');

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
        $request->request->set('available_currencies', ['EUR', 'USD', 'GBP']);

        $this->setEnvVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        $expectedShopInfo = [
            'name' => 'shop',
            'locale' => 'de-DE',
            'currency' => 'EUR',
            'additionalCurrencies' => ['EUR', 'USD', 'GBP'],
            'country' => 'DEU',
            'email' => 'info@test.com',
            'host' => 'localhost',
            'schema' => 'https',
            'basePath' => '/shop',
            'blueGreenDeployment' => true,
        ];

        $this->envConfigWriter->expects($this->once())->method('writeConfig')->with($connectionInfo, $expectedShopInfo);
        $this->shopConfigService->expects($this->once())->method('updateShop')->with($expectedShopInfo, $this->connection);

        $expectedAdmin = [
            'email' => 'test@test.com',
            'username' => 'admin',
            'firstName' => 'first',
            'lastName' => 'last',
            'password' => 'shopware',
            'locale' => 'de-DE',
        ];
        $this->adminConfigService->expects($this->once())->method('createAdmin')->with($expectedAdmin, $this->connection);

        $this->translator->method('trans')->willReturnCallback(fn (string $key): string => $key);

        $this->router->expects($this->once())->method('generate')
            ->with('installer.finish', ['completed' => true], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/finish?completed=1');

        $this->twig->expects($this->never())->method('render');

        $response = $this->controller->shopConfiguration($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/finish?completed=1', $response->getTargetUrl());

        static::assertFalse($session->has(DatabaseConnectionInformation::class));
        static::assertSame($expectedAdmin, $session->get('ADMIN_USER'));
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
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['iso3' => 'DEU', 'iso' => 'DE'],
                ['iso3' => 'GBR', 'iso' => 'GB'],
                ['iso3' => 'USA', 'iso' => 'US'],
            ]);

        $this->envConfigWriter->expects($this->once())->method('writeConfig')->willThrowException(new \Exception('Test Exception'));

        $this->translator->method('trans')->willReturnCallback(
            function (string $key): string {
                return $this->getLanguageTranslations()[$key] ?? $key;
            }
        );
        $this->twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/shop-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'error' => 'Test Exception',
                    'countryIsos' => [
                        ['iso3' => 'DEU', 'default' => true, 'translated' => 'shopware.installer.select_country_deu'],
                        ['iso3' => 'GBR', 'default' => false, 'translated' => 'shopware.installer.select_country_gbr'],
                        ['iso3' => 'USA', 'default' => false, 'translated' => 'shopware.installer.select_country_usa'],
                    ],
                    'currencyIsos' => ['EUR', 'USD', 'GBP'],
                    'languageIsos' => [
                        'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                        'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
                    ],
                    'allAvailableLanguages' => [
                        'de-DE' => ['id' => 'de-DE', 'label' => 'Deutsch'],
                        'en-GB' => ['id' => 'en-GB', 'label' => 'English'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                    ],
                    'parameters' => [
                        'config_shop_language' => 'de-DE',
                        'config_shop_currency' => 'EUR',
                    ],
                    'selectedLanguages' => [],
                ])
            )
            ->willReturn('config');

        $response = $this->controller->shopConfiguration($request);
        static::assertSame('config', $response->getContent());
    }

    public function testGetConfigurationCountryIsosSortedByAlphabetical(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setMethod('POST');
        $request->setSession($session);
        $request->attributes->set('_locale', 'de');

        $this->setEnvVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        // in non-alphabetical order
        $countries = [
            ['iso3' => 'GBR', 'iso' => 'GB'],
            ['iso3' => 'BGR', 'iso' => 'BG'],
            ['iso3' => 'EST', 'iso' => 'EE'],
            ['iso3' => 'HRV', 'iso' => 'HR'],
            ['iso3' => 'DEU', 'iso' => 'DE'],
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($countries);

        $this->envConfigWriter->expects($this->once())->method('writeConfig')->willThrowException(new \Exception('Test Exception'));

        $this->translator->method('trans')->willReturnCallback(
            function (string $key): string {
                $allTranslations = array_merge(
                    $this->getLanguageTranslations(),
                    $this->getCountryTranslations()
                );

                return $allTranslations[$key] ?? $key;
            }
        );

        $this->twig->expects($this->once())->method('render')->willReturnCallback(function (string $view, array $parameters): string {
            static::assertSame('@Installer/installer/shop-configuration.html.twig', $view);
            static::assertArrayHasKey('countryIsos', $parameters);

            $countryIsos = $parameters['countryIsos'];

            static::assertSame([
                'Bulgaria',
                'Croatia',
                'Estonia',
                'Germany',
                'Great Britain',
            ], array_column($countryIsos, 'translated'));

            return '';
        });

        $this->controller->shopConfiguration($request);
    }

    public static function shopConfigurationPresetProvider(): \Generator
    {
        yield ['de', 'de-DE', 'EUR', 'DEU'];
        yield ['en-US', 'en-US', 'USD', 'USA'];
        yield ['en', 'en-GB', 'GBP', 'GBR'];
    }

    /**
     * @return array<string, string>
     */
    private function getLanguageTranslations(): array
    {
        return [
            'shopware.installer.select_language_de-DE' => 'Deutsch',
            'shopware.installer.select_language_en-GB' => 'English',
            'shopware.installer.select_language_en-US' => 'English (US)',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getCountryTranslations(): array
    {
        return [
            'shopware.installer.select_country_gbr' => 'Great Britain',
            'shopware.installer.select_country_bgr' => 'Bulgaria',
            'shopware.installer.select_country_est' => 'Estonia',
            'shopware.installer.select_country_hrv' => 'Croatia',
            'shopware.installer.select_country_deu' => 'Germany',
        ];
    }
}
