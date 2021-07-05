<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Authentication\AbstractAuthenticationProvider;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
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

    protected Client $client;

    /**
     * @var array<string, string>
     */
    protected array $endpoints;

    private EntityRepositoryInterface $pluginRepo;

    private SystemConfigService $configService;

    private StoreService $storeService;

    /**
     * @var AbstractAuthenticationProvider|AbstractStoreRequestOptionsProvider|null
     */
    private $optionsProvider;

    private ?ExtensionLoader $extensionLoader;

    private ?InstanceService $instanceService;

    /**
     * @param AbstractAuthenticationProvider|AbstractStoreRequestOptionsProvider|null $optionsProvider
     *
     * @deprecated tag:v6.5.0 - Parameter $optionsProvider will only accept a AbstractStoreRequestOptionsProvider object in future versions.
     */
    public function __construct(
        array $endpoints,
        StoreService $storeService,
        EntityRepositoryInterface $pluginRepo,
        SystemConfigService $configService,
        $optionsProvider,
        ?ExtensionLoader $extensionLoader,
        Client $client,
        ?InstanceService $instanceService = null
    ) {
        $this->endpoints = $endpoints;
        $this->storeService = $storeService;
        $this->configService = $configService;
        $this->pluginRepo = $pluginRepo;
        $this->optionsProvider = $optionsProvider;
        $this->extensionLoader = $extensionLoader;
        $this->client = $client;
        $this->instanceService = $instanceService;
    }

    public function ping(): void
    {
        $this->client->get($this->endpoints['ping']);
    }

    public function loginWithShopwareId(string $shopwareId, string $password, Context $context): void
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $userId = $context->getSource()->getUserId();

        $response = $this->client->post(
            $this->endpoints['login'],
            [
                'query' => $this->getQueries($context),
                'json' => [
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                    'shopwareUserId' => $userId,
                ],
            ]
        );

        $data = \json_decode($response->getBody()->getContents(), true);

        $userToken = new ShopUserTokenStruct();
        $userToken->assign($data['shopUserToken']);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($data);
        $accessTokenStruct->setShopUserToken($userToken);

        $this->storeService->updateStoreToken($context, $accessTokenStruct);

        $this->configService->set('core.store.shopSecret', $accessTokenStruct->getShopSecret());
        $this->configService->set('core.store.shopwareId', $shopwareId);
    }

    /**
     * @return StoreLicenseStruct[]
     */
    public function getLicenseList(Context $context): array
    {
        $response = $this->client->get(
            $this->endpoints['my_plugin_licenses'],
            [
                'query' => $this->getQueries($context),
                'headers' => $this->getHeaders($context),
            ]
        );

        $data = \json_decode($response->getBody()->getContents(), true);

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
        if ($this->optionsProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        $extensionList = [];

        foreach ($extensionCollection as $extension) {
            $extensionList[] = [
                'name' => $extension->getName(),
                'version' => $extension->getVersion(),
            ];
        }

        return $this->getUpdateListFromStore($extensionList, $context);
    }

    /**
     * @return StoreUpdateStruct[]
     */
    public function getUpdatesList(PluginCollection $pluginCollection, string $hostName, Context $context): array
    {
        $pluginArray = [];

        foreach ($pluginCollection as $plugin) {
            $pluginArray[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        return $this->getUpdateListFromStore($pluginArray, $context, $hostName);
    }

    public function checkForViolations(
        Context $context,
        Collection $extensions,
        string $hostName
    ): void {
        $indexedExtensions = [];

        /** @var PluginEntity|ExtensionStruct $extension */
        foreach ($extensions as $extension) {
            $indexedExtensions[$extension->getName()] = $extension->getVersion();
        }

        $violations = $this->getLicenseViolations($context, $indexedExtensions, $hostName);
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
        Context $context,
        array $extensions,
        string $hostName
    ): array {
        $pluginData = [];

        foreach ($extensions as $name => $version) {
            $pluginData[] = [
                'name' => $name,
                'version' => $version,
            ];
        }

        $query = $this->getQueries($context);
        $query['hostName'] = $hostName;

        $response = $this->client->post(
            $this->endpoints['environment_information'],
            [
                'query' => $query,
                'headers' => $this->getHeaders($context),
                'json' => ['plugins' => $pluginData],
            ]
        );

        $data = \json_decode($response->getBody()->getContents(), true);

        return $this->getViolations($data['notices']);
    }

    public function getDownloadDataForPlugin(string $pluginName, Context $context): PluginDownloadDataStruct
    {
        $response = $this->client->get(
            str_replace('{pluginName}', $pluginName, $this->endpoints['plugin_download']),
            [
                'query' => $this->getQueries($context),
                'headers' => $this->getHeaders($context),
            ]
        );

        $data = \json_decode($response->getBody()->getContents(), true);
        $dataStruct = new PluginDownloadDataStruct();
        $dataStruct->assign($data);

        return $dataStruct;
    }

    public function getPluginCompatibilities(Context $context, string $futureVersion, PluginCollection $pluginCollection): array
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
                'query' => $this->getQueries($context),
                'headers' => $this->getHeaders($context),
                'json' => [
                    'futureShopwareVersion' => $futureVersion,
                    'plugins' => $pluginArray,
                ],
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getExtensionCompatibilities(Context $context, string $futureVersion, ExtensionCollection $extensionCollection): array
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
                'query' => $this->getQueries($context),
                'headers' => $this->getHeaders($context),
                'json' => [
                    'futureShopwareVersion' => $futureVersion,
                    'plugins' => $pluginArray,
                ],
            ]
        );

        return \json_decode($response->getBody()->getContents(), true);
    }

    public function isShopUpgradeable(): bool
    {
        $response = $this->client->get($this->endpoints['updater_permission'], [
            'query' => [
                'language' => 'en_GB',
                'shopwareVersion' => $this->getShopwareVersion(),
            ],
        ]);

        return \json_decode($response->getBody()->getContents(), true)['updateAllowed'];
    }

    public function signPayloadWithAppSecret(string $payload, string $appName): string
    {
        // use system context here because in cli we do not have a context
        $context = Context::createDefaultContext();

        $response = $this->client->post($this->endpoints['app_generate_signature'], [
            'query' => $this->getQueries($context),
            'headers' => $this->getHeaders($context),
            'json' => [
                'payload' => $payload,
                'appName' => $appName,
            ],
        ]);

        return \json_decode((string) $response->getBody(), true)['signature'];
    }

    public function listMyExtensions(ExtensionCollection $extensions, Context $context): ExtensionCollection
    {
        if ($this->optionsProvider === null || $this->extensionLoader === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $payload = ['plugins' => array_map(function (ExtensionStruct $e) {
                return [
                    'name' => $e->getName(),
                    'version' => $e->getVersion(),
                ];
            }, $extensions->getElements())];

            $response = $this->fetchLicenses($payload, $context);
        } catch (ClientException $e) {
            throw new StoreApiException($e);
        }

        $body = \json_decode($response->getBody()->getContents(), true);

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
        if ($this->optionsProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $this->client->post(sprintf($this->endpoints['cancel_license'], $licenseId), [
                'query' => $this->getQueries($context),
                'headers' => $this->getHeaders($context),
            ]);
        } catch (ClientException $e) {
            if ($e->hasResponse() && $e->getResponse() !== null) {
                $error = \json_decode((string) $e->getResponse()->getBody(), true);

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
        if ($this->optionsProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $this->client->post(
                sprintf($this->endpoints['create_rating'], $rating->getExtensionId()),
                [
                    'query' => $this->getQueries($context),
                    'headers' => $this->getHeaders($context),
                    'json' => $rating,
                ]
            );
        } catch (ClientException $e) {
            throw new StoreApiException($e);
        }
    }

    public function getLicenses(Context $context): array
    {
        if ($this->optionsProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $response = $this->client->get(
                $this->endpoints['my_licenses'],
                [
                    'query' => $this->getHeaders($context),
                    'headers' => $this->getHeaders($context),
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

    protected function fetchLicenses(array $payload, Context $context): ResponseInterface
    {
        return $this->client->post($this->endpoints['my_extensions'], [
            'query' => $this->getQueries($context),
            'headers' => $this->getHeaders($context),
            'json' => $payload,
        ]);
    }

    protected function getHeaders(Context $context): array
    {
        if ($this->optionsProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        return $this->optionsProvider->getAuthenticationHeader($context);
    }

    /**
     * @deprecated tag:v6.5.0 when AbstractStoreRequestDataProvider is required
     */
    protected function getQueries(Context $context): array
    {
        if ($this->optionsProvider instanceof AbstractStoreRequestOptionsProvider) {
            return $this->optionsProvider->getDefaultQueryParameters($context);
        }

        return $this->storeService->getDefaultQueryParametersFromContext($context);
    }

    /**
     * @deprecated tag:v6.5.0 when AbstractStoreRequestDataProvider is required
     */
    protected function getShopwareVersion(): string
    {
        if ($this->instanceService !== null) {
            return $this->instanceService->getShopwareVersion();
        }

        return $this->storeService->getShopwareVersion();
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

    /**
     * @return StoreUpdateStruct[]
     */
    private function getUpdateListFromStore(array $extensionList, Context $context, ?string $hostName = null): array
    {
        $query = $this->getQueries($context);

        if ($hostName) {
            $query['hostName'] = $hostName;
        }

        try {
            $headers = $this->getHeaders($context);
        } catch (StoreTokenMissingException $e) {
            $headers = [];
        }

        $response = $this->client->post(
            $this->endpoints['my_plugin_updates'],
            [
                'query' => $query,
                'headers' => $headers,
                'json' => ['plugins' => $extensionList],
            ]
        );

        $data = \json_decode($response->getBody()->getContents(), true);

        if (!\array_key_exists('data', $data) || !\is_array($data['data'])) {
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
}
