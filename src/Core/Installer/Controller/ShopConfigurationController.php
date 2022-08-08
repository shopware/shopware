<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Installer\Configuration\AdminConfigurationService;
use Shopware\Core\Installer\Configuration\EnvConfigWriter;
use Shopware\Core\Installer\Configuration\ShopConfigurationService;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @phpstan-type Shop array{name: string, locale: string, currency: string, additionalCurrencies: null|list<string>, country: string, email: string, host: string, basePath: string, https: bool, blueGreenDeployment: bool}
 * @phpstan-type AdminUser array{email: string, username: string, firstName: string, lastName: string, password: string}
 */
class ShopConfigurationController extends InstallerController
{
    private DatabaseConnectionFactory $connectionFactory;

    private EnvConfigWriter $envConfigWriter;

    private ShopConfigurationService $shopConfigurationService;

    private AdminConfigurationService $adminConfigurationService;

    /**
     * @var array<string, string>
     */
    private array $supportedLanguages;

    /**
     * @var list<string>
     */
    private array $supportedCurrencies;

    /**
     * @param array<string, string> $supportedLanguages
     * @param list<string> $supportedCurrencies
     */
    public function __construct(
        DatabaseConnectionFactory $connectionFactory,
        EnvConfigWriter $envConfigWriter,
        ShopConfigurationService $shopConfigurationService,
        AdminConfigurationService $adminConfigurationService,
        array $supportedLanguages,
        array $supportedCurrencies
    ) {
        $this->connectionFactory = $connectionFactory;
        $this->envConfigWriter = $envConfigWriter;
        $this->shopConfigurationService = $shopConfigurationService;
        $this->adminConfigurationService = $adminConfigurationService;
        $this->supportedLanguages = $supportedLanguages;
        $this->supportedCurrencies = $supportedCurrencies;
    }

    /**
     * @Since("6.4.13.0")
     * @Route("/installer/shop-configuration", name="installer.shop-configuration", methods={"GET", "POST"})
     */
    public function shopConfiguration(Request $request): Response
    {
        $session = $request->getSession();
        /** @var DatabaseConnectionInformation|null $connectionInfo */
        $connectionInfo = $session->get(DatabaseConnectionInformation::class);

        if (!$connectionInfo) {
            return $this->redirectToRoute('installer.database-configuration');
        }

        $connection = $this->connectionFactory->getConnection($connectionInfo);

        $error = null;

        if ($request->getMethod() === 'POST') {
            $adminUser = [
                'email' => (string) $request->request->get('config_admin_email'),
                'username' => (string) $request->request->get('config_admin_username'),
                'firstName' => (string) $request->request->get('config_admin_firstName'),
                'lastName' => (string) $request->request->get('config_admin_lastName'),
                'password' => (string) $request->request->get('config_admin_password'),
                'locale' => $this->supportedLanguages[$request->attributes->get('_locale')],
            ];

            /** @var list<string>|null $availableCurrencies */
            $availableCurrencies = $request->request->get('available_currencies');
            $shop = [
                'name' => (string) $request->request->get('config_shopName'),
                'locale' => (string) $request->request->get('config_shop_language'),
                'currency' => (string) $request->request->get('config_shop_currency'),
                'additionalCurrencies' => $availableCurrencies ?: null,
                'country' => (string) $request->request->get('config_shop_country'),
                'email' => (string) $request->request->get('config_mail'),
                'host' => (string) $_SERVER['HTTP_HOST'],
                'https' => (bool) $_SERVER['HTTPS'],
                'basePath' => str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']),
                'blueGreenDeployment' => (bool) $session->get(BlueGreenDeploymentService::ENV_NAME),
            ];

            try {
                $this->envConfigWriter->writeConfig($connectionInfo, $shop);

                $this->shopConfigurationService->updateShop($shop, $connection);
                $this->adminConfigurationService->createAdmin($adminUser, $connection);

                return $this->redirectToRoute('installer.shop-configuration');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        if (!$request->request->has('config_shop_language')) {
            $request->request->set('config_shop_language', $this->supportedLanguages[$request->attributes->get('_locale')]);
        }

        return $this->renderInstaller(
            '@Installer/installer/shop-configuration.html.twig',
            [
                'error' => $error,
                'countryIsos' => $this->getCountryIsos($connection, $request->attributes->get('_locale')),
                'languageIsos' => $this->supportedLanguages,
                'currencyIsos' => $this->supportedCurrencies,
                'parameters' => $request->request->all(),
            ]
        );
    }

    /**
     * @return array<int, array{iso3: string, default: bool}>
     */
    private function getCountryIsos(Connection $connection, string $currentLocale): array
    {
        /** @var array<int, array{iso3: string, iso: string}> $countries */
        $countries = $connection->fetchAllAssociative('SELECT iso3, iso FROM country');

        // formatting string e.g. "en-GB" to "GB"
        $localeIsoCode = mb_substr($this->supportedLanguages[$currentLocale], -2, 2);

        // flattening array
        $countryIsos = array_map(function ($country) use ($localeIsoCode) {
            return [
                'iso3' => $country['iso3'],
                'default' => $country['iso'] === $localeIsoCode,
            ];
        }, $countries);

        return $countryIsos;
    }
}
