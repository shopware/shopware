<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Authentication\AbstractAuthenticationProvider;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\LicenseStruct;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Store\Struct\StoreActionStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseSubscriptionStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseTypeStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseViolationStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseViolationTypeStruct;
use Shopware\Core\Framework\Store\Struct\StoreUpdateStruct;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class StoreClient
{
    private const PLUGIN_LICENSE_VIOLATION_EXTENSION_KEY = 'licenseViolation';
    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';
    private const SHOPWARE_SHOP_SECRET_HEADER = 'X-Shopware-Shop-Secret';

    private Client $client;

    private EntityRepositoryInterface $pluginRepo;

    private SystemConfigService $configService;

    private StoreService $storeService;

    private ?AbstractAuthenticationProvider $authenticationProvider;

    private ?ExtensionLoader $extensionLoader;

    private array $endpoints;

    final public function __construct(
        array $endpoints,
        StoreService $storeService,
        EntityRepositoryInterface $pluginRepo,
        SystemConfigService $configService,
        ?AbstractAuthenticationProvider $authenticationProvider,
        ?ExtensionLoader $extensionLoader,
        Client $client
    ) {
        $this->endpoints = $endpoints;
        $this->storeService = $storeService;
        $this->configService = $configService;
        $this->pluginRepo = $pluginRepo;
        $this->authenticationProvider = $authenticationProvider;
        $this->extensionLoader = $extensionLoader;
        $this->client = $client;
    }

    public function ping(): void
    {
        $this->client->get($this->endpoints['ping']);
    }

    public function loginWithShopwareId(string $shopwareId, string $password, string $language, Context $context): AccessTokenStruct
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $response = $this->client->post(
            $this->endpoints['login'],
            [
                'body' => json_encode([
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                    'shopwareUserId' => $context->getSource()->getUserId(),
                ]),
                'query' => $this->storeService->getDefaultQueryParameters($language),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $userToken = new ShopUserTokenStruct();
        $userToken->assign($data['shopUserToken']);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($data);
        $accessTokenStruct->setShopUserToken($userToken);

        return $accessTokenStruct;
    }

    /**
     * @return StoreLicenseStruct[]
     */
    public function getLicenseList(string $storeToken, string $language, Context $context): array
    {
        $response = $this->client->get(
            $this->endpoints['my_plugin_licenses'],
            [
                'query' => $this->storeService->getDefaultQueryParameters($language),
                'headers' => $this->getHeaders($storeToken),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $licenseList = [];
        $installedPlugins = [];

        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        foreach ($pluginCollection as $plugin) {
            $installedPlugins[$plugin->getName()] = $plugin->getVersion();
        }

        foreach ($data['data'] as $license) {
            $licenseStruct = new StoreLicenseStruct();
            $licenseStruct->assign($license);

            $licenseStruct->setInstalled(\array_key_exists($licenseStruct->getTechnicalPluginName(), $installedPlugins));
            if (isset($license['availableVersion'])) {
                if ($licenseStruct->getInstalled()) {
                    $installedVersion = $installedPlugins[$licenseStruct->getTechnicalPluginName()];

                    $licenseStruct->setUpdateAvailable(version_compare($installedVersion, $licenseStruct->getAvailableVersion()) === -1);
                } else {
                    $licenseStruct->setUpdateAvailable(false);
                }
            }
            if (isset($license['type']['name'])) {
                $type = new StoreLicenseTypeStruct();
                $type->assign($license['type']);
                $licenseStruct->setType($type);
            }
            if (isset($license['subscription']['expirationDate'])) {
                $subscription = new StoreLicenseSubscriptionStruct();
                $subscription->assign($license['subscription']);
                $licenseStruct->setSubscription($subscription);
            }

            $licenseList[] = $licenseStruct;
        }

        return $licenseList;
    }

    /**
     * @return StoreUpdateStruct[]
     */
    public function getExtensionUpdateList(ExtensionCollection $extensionCollection, Context $context): array
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        $list = [];

        foreach ($extensionCollection as $extension) {
            $list[] = [
                'name' => $extension->getName(),
                'version' => $extension->getVersion(),
            ];
        }

        $query = $this->storeService->getDefaultQueryParameters('en-GB', false);

        $token = null;

        try {
            $token = $this->authenticationProvider->getUserStoreToken($context);
        } catch (StoreTokenMissingException $e) {
        }

        $response = $this->client->post(
            $this->endpoints['my_plugin_updates'],
            [
                'query' => $query,
                'body' => json_encode(['plugins' => $list]),
                'headers' => $this->getHeaders($token),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        if (!\array_key_exists('data', $data)) {
            return [];
        }

        $updateList = [];
        foreach ($data['data'] as $update) {
            $updateStruct = new StoreUpdateStruct();
            $updateStruct->assign($update);
            $updateList[] = $updateStruct;
        }

        return $updateList;
    }

    /**
     * @return StoreUpdateStruct[]
     */
    public function getUpdatesList(?string $storeToken, PluginCollection $pluginCollection, string $language, string $hostName, Context $context): array
    {
        $pluginArray = [];

        foreach ($pluginCollection as $plugin) {
            $pluginArray[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        $query = $this->storeService->getDefaultQueryParameters($language, false);
        $query['hostName'] = $hostName;

        $response = $this->client->post(
            $this->endpoints['my_plugin_updates'],
            [
                'query' => $query,
                'body' => json_encode(['plugins' => $pluginArray]),
                'headers' => $this->getHeaders($storeToken),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $updateList = [];
        foreach ($data['data'] as $update) {
            $updateStruct = new StoreUpdateStruct();
            $updateStruct->assign($update);
            $updateList[] = $updateStruct;
        }

        return $updateList;
    }

    public function checkForViolations(
        ?string $storeToken,
        Collection $extensions,
        string $language,
        string $hostName
    ): void {
        $indexedExtensions = [];

        /** @var PluginEntity|ExtensionStruct $extension */
        foreach ($extensions as $extension) {
            $indexedExtensions[$extension->getName()] = $extension->getVersion();
        }

        $violations = $this->getLicenseViolations($storeToken, $indexedExtensions, $language, $hostName);
        $indexed = [];
        /** @var StoreLicenseViolationStruct $violation */
        foreach ($violations as $violation) {
            $indexed[$violation->getName()] = $violation;
        }

        foreach ($extensions as $extension) {
            if (isset($indexed[$extension->getName()])) {
                $extension->addExtension(self::PLUGIN_LICENSE_VIOLATION_EXTENSION_KEY, $indexed[$extension->getName()]);
            }
        }
    }

    public function getLicenseViolations(
        ?string $storeToken,
        array $extensions,
        string $language,
        string $hostName
    ): array {
        $pluginData = [];

        foreach ($extensions as $name => $version) {
            $pluginData[] = [
                'name' => $name,
                'version' => $version,
            ];
        }

        $query = $this->storeService->getDefaultQueryParameters($language, false);
        $query['hostName'] = $hostName;

        $response = $this->client->post(
            $this->endpoints['environment_information'],
            [
                'query' => $query,
                'body' => json_encode(['plugins' => $pluginData]),
                'headers' => $this->getHeaders($storeToken),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        return $this->getViolations($data['notices']);
    }

    public function getDownloadDataForPlugin(string $pluginName, string $storeToken, string $language, bool $checkLicenseDomain = true): PluginDownloadDataStruct
    {
        $response = $this->client->get(
            str_replace('{pluginName}', $pluginName, $this->endpoints['plugin_download']),
            [
                'query' => $this->storeService->getDefaultQueryParameters($language, $checkLicenseDomain),
                'headers' => $this->getHeaders($storeToken),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);
        $dataStruct = new PluginDownloadDataStruct();
        $dataStruct->assign($data);

        return $dataStruct;
    }

    public function getPluginCompatibilities(string $futureVersion, string $language, PluginCollection $pluginCollection): array
    {
        $pluginArray = [];

        foreach ($pluginCollection as $plugin) {
            $pluginArray[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        $response = $this->client->post(
            $this->endpoints['updater_extension_compatibility'],
            [
                'query' => $this->storeService->getDefaultQueryParameters($language, false),
                'headers' => $this->getHeaders(),
                'json' => [
                    'futureShopwareVersion' => $futureVersion,
                    'plugins' => $pluginArray,
                ],
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    public function getExtensionCompatibilities(string $futureVersion, string $language, ExtensionCollection $extensionCollection): array
    {
        $pluginArray = [];

        foreach ($extensionCollection as $extension) {
            $pluginArray[] = [
                'name' => $extension->getName(),
                'version' => $extension->getVersion(),
            ];
        }

        $response = $this->client->post(
            $this->endpoints['updater_extension_compatibility'],
            [
                'query' => $this->storeService->getDefaultQueryParameters($language, false),
                'headers' => $this->getHeaders(),
                'json' => [
                    'futureShopwareVersion' => $futureVersion,
                    'plugins' => $pluginArray,
                ],
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    public function isShopUpgradeable(): bool
    {
        $response = $this->client->get($this->endpoints['updater_permission'], [
            'query' => $this->storeService->getDefaultQueryParameters('en-GB', false),
            'headers' => $this->getHeaders(),
        ]);

        return json_decode((string) $response->getBody(), true)['updateAllowed'];
    }

    public function signPayloadWithAppSecret(string $payload, string $appName): string
    {
        $response = $this->client->post($this->endpoints['app_generate_signature'], [
            'query' => $this->storeService->getDefaultQueryParameters('en-GB'),
            'headers' => $this->getHeaders(),
            'json' => [
                'payload' => $payload,
                'appName' => $appName,
            ],
        ]);

        return json_decode((string) $response->getBody(), true)['signature'];
    }

    public function listMyExtensions(ExtensionCollection $extensions, Context $context): ExtensionCollection
    {
        if ($this->authenticationProvider === null || $this->extensionLoader === null) {
            throw new \RuntimeException('App Store is not active');
        }

        $language = $this->storeService->getLanguageByContext($context);
        $storeToken = $this->authenticationProvider->getUserStoreToken($context);

        try {
            $response = $this->client->post($this->endpoints['my_extensions'], [
                'query' => $this->storeService->getDefaultQueryParameters($language, false),
                'headers' => $this->getHeaders($storeToken),
                'json' => ['plugins' => array_map(function (ExtensionStruct $e) {
                    return [
                        'name' => $e->getName(),
                        'version' => $e->getVersion(),
                    ];
                }, $extensions->getElements())],
            ]);
        } catch (ClientException $e) {
            throw new StoreApiException($e);
        }

        $body = json_decode((string) $response->getBody(), true);

        $myExtensions = new ExtensionCollection();

        foreach ($body as $item) {
            $extension = $this->extensionLoader->loadFromArray($context, $item['extension']);
            $extension->setSource(ExtensionStruct::SOURCE_STORE);
            if (isset($item['license'])) {
                $extension->setStoreLicense(LicenseStruct::fromArray($item['license']));
            }

            if (isset($item['update'])) {
                $extension->setVersion($item['update']['installedVersion']);
                $extension->setLatestVersion($item['update']['availableVersion']);
                $extension->setUpdateSource(ExtensionStruct::SOURCE_STORE);
            }

            $myExtensions->set($extension->getName(), $extension);
        }

        return $myExtensions;
    }

    public function cancelSubscription(int $licenseId, Context $context): void
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $this->client->post(sprintf($this->endpoints['cancel_license'], $licenseId), [
                'query' => $this->storeService->getDefaultQueryParameters('en-GB', false),
                'headers' => $this->getHeaders($this->authenticationProvider->getUserStoreToken($context)),
            ]);
        } catch (ClientException $e) {
            if ($e->hasResponse() && $e->getResponse() !== null) {
                $error = json_decode((string) $e->getResponse()->getBody(), true);

                // It's okay when its already canceled
                if (isset($error['type']) && $error['type'] === 'EXTENSION_LICENSE_IS_ALREADY_CANCELLED') {
                    return;
                }
            }

            throw new StoreApiException($e);
        }
    }

    public function createRating(ReviewStruct $rating, Context $context): void
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $this->client->post(
                sprintf($this->endpoints['create_rating'], $rating->getExtensionId()),
                [
                    'query' => $this->storeService->getDefaultQueryParameters('en-GB', false),
                    'headers' => $this->getHeaders($this->authenticationProvider->getUserStoreToken($context)),
                    'json' => $rating,
                ]
            );
        } catch (ClientException $e) {
            throw new StoreApiException($e);
        }
    }

    public function getLicenses(Context $context): array
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $response = $this->client->get(
                $this->endpoints['my_licenses'],
                [
                    'query' => $this->storeService->getDefaultQueryParameters('en-GB'),
                    'headers' => $this->getHeaders($this->authenticationProvider->getUserStoreToken($context)),
                ]
            );
        } catch (ClientException $e) {
            throw new StoreApiException($e);
        }

        $body = json_decode($response->getBody()->getContents(), true);

        return [
            'headers' => $response->getHeaders(),
            'data' => $body,
        ];
    }

    /**
     * @return StoreLicenseViolationStruct[]
     */
    private function getViolations(array $violationsData): array
    {
        $violations = [];
        foreach ($violationsData as $violationData) {
            $violationData['actions'] = $this->getActions($violationData['actions'] ?? []);
            $violationData['type'] = (new StoreLicenseViolationTypeStruct())->assign($violationData['type']);
            $expired = new StoreLicenseViolationStruct();
            $expired->assign($violationData);
            $violations[] = $expired;
        }

        return $violations;
    }

    /**
     * @return StoreActionStruct[]
     */
    private function getActions(array $actionsData): array
    {
        $actions = [];
        foreach ($actionsData as $actionData) {
            $action = new StoreActionStruct();
            $action->assign($actionData);
            $actions[] = $action;
        }

        return $actions;
    }

    private function getHeaders(?string $storeToken = null): array
    {
        $headers = $this->client->getConfig('headers');

        if ($storeToken) {
            $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER] = $storeToken;
        }

        $shopSecret = $this->configService->get('core.store.shopSecret');
        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        return $headers;
    }
}
