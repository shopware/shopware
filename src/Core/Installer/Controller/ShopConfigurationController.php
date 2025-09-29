<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Configuration\AdminConfigurationService;
use Shopware\Core\Installer\Configuration\EnvConfigWriter;
use Shopware\Core\Installer\Configuration\ShopConfigurationService;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @phpstan-type Shop array{name: string, locale: string, currency: string, additionalCurrencies: null|list<string>, country: string, email: string, host: string, basePath: string, schema: string, blueGreenDeployment: bool}
 * @phpstan-type AdminUser array{email: string, username: string, firstName: string, lastName: string, password: string}
 *
 * @phpstan-import-type SupportedLanguages from \Shopware\Core\Installer\Controller\InstallerController
 */
#[Package('framework')]
class ShopConfigurationController extends InstallerController
{
    /**
     * @param SupportedLanguages $supportedLanguages
     * @param list<string> $supportedCurrencies
     */
    public function __construct(
        private readonly DatabaseConnectionFactory $connectionFactory,
        private readonly EnvConfigWriter $envConfigWriter,
        private readonly ShopConfigurationService $shopConfigurationService,
        private readonly AdminConfigurationService $adminConfigurationService,
        private readonly TranslatorInterface $translator,
        private readonly TranslationConfig $translationConfig,
        private readonly array $supportedLanguages,
        private readonly array $supportedCurrencies
    ) {
    }

    #[Route(path: '/installer/configuration', name: 'installer.configuration', methods: ['GET', 'POST'])]
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
                'locale' => $this->supportedLanguages[$request->attributes->get('_locale')]['id'],
            ];

            /** @var list<string> $availableCurrencies */
            $availableCurrencies = $request->request->all('available_currencies');

            /** @var list<string> $selectedLanguages */
            $selectedLanguages = $request->request->all('selected_languages') ?: [];

            // Always include the selected shop language
            $shopLanguage = (string) $request->request->get('config_shop_language');
            if (!\in_array($shopLanguage, $selectedLanguages, true) && !\in_array($shopLanguage, ['de-DE', 'en-GB'], true)) {
                $selectedLanguages[] = $shopLanguage;
            }

            // Use all available languages from TranslationConfigLoader
            $availableLanguages = $this->getAllAvailableLanguages();
            $selectedLanguages = array_map(function (string $iso) use ($availableLanguages) {
                // already a full locale like xx-XX?
                if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $iso)) {
                    return $iso;
                }

                return isset($availableLanguages[$iso]['id']) ? $availableLanguages[$iso]['id'] : null;
            }, $selectedLanguages);

            $schema = 'http';
            // This is for supporting Apache 2.2
            if (\array_key_exists('HTTPS', $_SERVER) && mb_strtolower((string) $_SERVER['HTTPS']) === 'on') {
                $schema = 'https';
            }
            if (\array_key_exists('REQUEST_SCHEME', $_SERVER)) {
                $schema = $_SERVER['REQUEST_SCHEME'];
            }

            $shop = [
                'name' => (string) $request->request->get('config_shopName'),
                'locale' => (string) $request->request->get('config_shop_language'),
                'currency' => (string) $request->request->get('config_shop_currency'),
                'additionalCurrencies' => $availableCurrencies ?: null,
                'country' => (string) $request->request->get('config_shop_country'),
                'email' => (string) $request->request->get('config_mail'),
                'host' => (string) $_SERVER['HTTP_HOST'],
                'schema' => $schema,
                'basePath' => str_replace('/index.php', '', (string) $_SERVER['SCRIPT_NAME']),
                'blueGreenDeployment' => (bool) $session->get(BlueGreenDeploymentService::ENV_NAME),
            ];

            try {
                $this->envConfigWriter->writeConfig($connectionInfo, $shop);

                // create admin user first, if there is a validation error we don't need to update shop
                // and create sales channel
                $this->adminConfigurationService->createAdmin($adminUser, $connection);
                $this->shopConfigurationService->updateShop($shop, $connection);

                $session->set('ADMIN_USER', $adminUser);
                $session->set('SELECTED_LANGUAGES', $selectedLanguages);

                // Check if user selected any languages
                if (empty($selectedLanguages)) {
                    // No languages selected, go directly to finish page
                    $session->remove(DatabaseConnectionInformation::class);

                    return $this->redirectToRoute('installer.finish', ['completed' => true]);
                }

                // Languages selected, go to translation step
                return $this->redirectToRoute('installer.translation');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        if (!$request->request->has('config_shop_language')) {
            $request->request->set('config_shop_language', $this->supportedLanguages[$request->attributes->get('_locale')]['id']);
        }

        $locale = $request->attributes->get('_locale');
        /** @var array<string, array{currency: string }> $preselection */
        $preselection = $this->container->getParameter('shopware.installer.configurationPreselection');

        $parameters = $request->request->all();
        $parameters['config_shop_currency'] ??= $preselection[$locale]['currency'] ?? 'EUR';

        return $this->renderInstaller(
            '@Installer/installer/shop-configuration.html.twig',
            [
                'error' => $error,
                'countryIsos' => $this->getCountryIsos($connection, $locale),
                'languageIsos' => $this->supportedLanguages,
                'allAvailableLanguages' => $this->getAllAvailableLanguages(),
                'currencyIsos' => $this->supportedCurrencies,
                'parameters' => $parameters,
                'selectedLanguages' => $request->request->all('selected_languages') ?: [],
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
        $localeIsoCode = mb_substr($this->supportedLanguages[$currentLocale]['id'], -2, 2);

        // flattening array
        $countryIsos = array_map(fn ($country) => [
            'iso3' => $country['iso3'],
            'default' => $country['iso'] === $localeIsoCode,
            'translated' => $this->translator->trans('shopware.installer.select_country_' . mb_strtolower($country['iso3'])),
        ], $countries);

        usort(/**
         * sorting country by translated
         *
         * @param array<string, string> $first
         * @param array<string, string> $second
         */ $countryIsos, fn (array $first, array $second) => strcmp($first['translated'], $second['translated']));

        return $countryIsos;
    }

    /**
     * Get all available languages from TranslationConfigLoader
     *
     * @return array<string, array{id: string, label: string}>
     */
    private function getAllAvailableLanguages(): array
    {
        // Always include default languages for the UI
        $languages = [
            'de-DE' => [
                'id' => 'de-DE',
                'label' => $this->translator->trans('shopware.installer.select_language_de-DE'),
            ],
            'en-GB' => [
                'id' => 'en-GB',
                'label' => $this->translator->trans('shopware.installer.select_language_en-GB'),
            ],
        ];

        foreach ($this->translationConfig->languages as $language) {
            $translationKey = 'shopware.installer.select_language_' . $language->locale;
            $translatedName = $this->translator->trans($translationKey);

            $label = ($translatedName !== $translationKey) ? $translatedName : $language->name;

            $languages[$language->locale] = [
                'id' => $language->locale,
                'label' => $label,
            ];
        }

        return $languages;
    }
}
