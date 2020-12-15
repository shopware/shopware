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
use Shopware\Core\Framework\Store\Authentication\AbstractAuthenticationProvider;
use Shopware\Core\Framework\Store\Search\ExtensionCriteria;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\CartStruct;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class StoreClient
{
    public const PLUGIN_LICENSE_VIOLATION_EXTENSION_KEY = 'licenseViolation';
    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';

    private const SHOPWARE_SHOP_SECRET_HEADER = 'X-Shopware-Shop-Secret';

    private const SBP_API_URL_PING = '/ping';
    private const SBP_API_URL_LOGIN = '/swplatform/login';
    private const SBP_API_URL_LICENSES = '/swplatform/licenses';
    private const SBP_API_URL_PLUGIN_LICENSES = '/swplatform/pluginlicenses';
    private const SBP_API_URL_PLUGIN_UPDATES = '/swplatform/pluginupdates';
    private const SBP_API_URL_PLUGIN_VIOLATIONS = '/swplatform/environmentinformation';
    private const SBP_API_URL_PLUGIN_COMPATIBILITY = '/swplatform/autoupdate';
    private const SBP_API_URL_PLUGIN_DOWNLOAD_INFO = '/swplatform/pluginfiles/{pluginName}';
    private const SBP_API_URL_UPDATE_PERMISSIONS = '/swplatform/autoupdate/permission';
    private const SBP_API_URL_GENERATE_SIGNATURE = '/swplatform/generatesignature';
    private const SBP_API_LIST_CATEGORIES = '/swplatform/extensionstore/categories';
    private const SBP_API_LIST_EXTENSIONS = '/swplatform/extensionstore/extensions';
    private const SBP_API_DETAIL_EXTENSION = '/swplatform/extensionstore/extensions/%d';
    private const SBP_API_DETAIL_EXTENSION_REVIEWS = '/swplatform/extensionstore/extensions/%d/reviews';
    private const SBP_API_CREATE_CART = '/swplatform/extensionstore/baskets';
    private const SBP_API_ORDER_CART = '/swplatform/extensionstore/orders';
    private const SBP_API_CANCEL_LICENSE = '/swplatform/pluginlicenses/%s/cancel';
    private const SBP_API_LIST_FILTERS = '/swplatform/extensionstore/extensions/filter';

    /**
     * @var Client|null
     */
    private $client;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var StoreService
     */
    private $storeService;

    /**
     * @var AbstractAuthenticationProvider|null
     */
    private $authenticationProvider;

    final public function __construct(
        StoreService $storeService,
        EntityRepositoryInterface $pluginRepo,
        SystemConfigService $configService,
        ?AbstractAuthenticationProvider $authenticationProvider
    ) {
        $this->storeService = $storeService;
        $this->configService = $configService;
        $this->pluginRepo = $pluginRepo;
        $this->authenticationProvider = $authenticationProvider;
    }

    public function ping(): void
    {
        $this->getClient()->get(self::SBP_API_URL_PING);
    }

    public function loginWithShopwareId(string $shopwareId, string $password, string $language, Context $context): AccessTokenStruct
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $response = $this->getClient()->post(
            self::SBP_API_URL_LOGIN,
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
        $response = $this->getClient()->get(
            self::SBP_API_URL_PLUGIN_LICENSES,
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

        $response = $this->getClient()->post(
            self::SBP_API_URL_PLUGIN_UPDATES,
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
        PluginCollection $plugins,
        string $language,
        string $hostName,
        Context $context
    ): void {
        $violations = $this->getLicenseViolations($storeToken, $plugins, $language, $hostName, $context);
        $indexed = [];
        /** @var StoreLicenseViolationStruct $violation */
        foreach ($violations as $violation) {
            $indexed[$violation->getName()] = $violation;
        }

        foreach ($plugins as $plugin) {
            if (isset($indexed[$plugin->getName()])) {
                $plugin->addExtension(self::PLUGIN_LICENSE_VIOLATION_EXTENSION_KEY, $indexed[$plugin->getName()]);
            }
        }
    }

    public function getLicenseViolations(
        ?string $storeToken,
        PluginCollection $plugins,
        string $language,
        string $hostName,
        Context $context
    ): array {
        $pluginData = [];

        foreach ($plugins as $plugin) {
            $pluginData[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        $query = $this->storeService->getDefaultQueryParameters($language, false);
        $query['hostName'] = $hostName;

        $response = $this->getClient()->post(
            self::SBP_API_URL_PLUGIN_VIOLATIONS,
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
        $response = $this->getClient()->get(
            str_replace('{pluginName}', $pluginName, self::SBP_API_URL_PLUGIN_DOWNLOAD_INFO),
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

        $response = $this->getClient()->post(
            self::SBP_API_URL_PLUGIN_COMPATIBILITY,
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
        $response = $this->getClient()->get(self::SBP_API_URL_UPDATE_PERMISSIONS, [
            'query' => $this->storeService->getDefaultQueryParameters('en-GB', false),
            'headers' => $this->getHeaders(),
        ]);

        return json_decode((string) $response->getBody(), true)['updateAllowed'];
    }

    public function signPayloadWithAppSecret(string $payload, string $appName): string
    {
        $response = $this->getClient()->post(self::SBP_API_URL_GENERATE_SIGNATURE, [
            'headers' => $this->getHeaders(),
            'json' => [
                'payload' => $payload,
                'appName' => $appName,
            ],
        ]);

        return json_decode((string) $response->getBody(), true)['signature'];
    }

    public function getCategories(Context $context): array
    {
        $language = $this->storeService->getLanguageByContext($context);

        $response = $this->getClient()->get(self::SBP_API_LIST_CATEGORIES, [
            'query' => $this->storeService->getDefaultQueryParameters($language, false),
            'headers' => $this->getHeaders(),
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function listExtensions(ExtensionCriteria $criteria, Context $context): array
    {
        $language = $this->storeService->getLanguageByContext($context);

        $response = $this->getClient()->get(self::SBP_API_LIST_EXTENSIONS, [
            'query' => array_merge($this->storeService->getDefaultQueryParameters($language, false), $criteria->getQueryParameter()),
            'headers' => $this->getHeaders(),
        ]);

        $body = json_decode((string) $response->getBody(), true);

        return [
            'headers' => $response->getHeaders(),
            'data' => $body,
        ];
    }

    public function listListingFilters(Context $context): array
    {
        $language = $this->storeService->getLanguageByContext($context);

        $response = $this->getClient()->get(self::SBP_API_LIST_FILTERS, [
            'query' => $this->storeService->getDefaultQueryParameters($language, false),
            'headers' => $this->getHeaders(),
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function extensionDetail(int $id, Context $context): array
    {
        $language = $this->storeService->getLanguageByContext($context);

        $response = $this->getClient()->get(sprintf(self::SBP_API_DETAIL_EXTENSION, $id), [
            'query' => $this->storeService->getDefaultQueryParameters($language, false),
            'headers' => $this->getHeaders(),
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function extensionDetailReviews(int $id, ExtensionCriteria $criteria, Context $context): array
    {
        $language = $this->storeService->getLanguageByContext($context);

        $response = $this->getClient()->get(sprintf(self::SBP_API_DETAIL_EXTENSION_REVIEWS, $id), [
            'query' => array_merge($this->storeService->getDefaultQueryParameters($language, false), $criteria->getQueryParameter()),
            'headers' => $this->getHeaders(),
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function createCart(int $extensionId, int $variantId, Context $context): CartStruct
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        $language = $this->storeService->getLanguageByContext($context);

        $response = $this->getClient()->post(self::SBP_API_CREATE_CART, [
            'query' => $this->storeService->getDefaultQueryParameters($language, false),
            'headers' => $this->getHeaders($this->authenticationProvider->getUserStoreToken($context)),
            'json' => [
                'extensions' => [
                    [
                        'extensionId' => $extensionId,
                        'variantId' => $variantId,
                    ],
                ],
            ],
        ]);

        return CartStruct::fromArray(json_decode((string) $response->getBody(), true));
    }

    public function orderCart(CartStruct $cartStruct, Context $context): void
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        $this->getClient()->post(self::SBP_API_ORDER_CART, [
            'query' => $this->storeService->getDefaultQueryParameters('en-GB', false),
            'headers' => $this->getHeaders($this->authenticationProvider->getUserStoreToken($context)),
            'json' => $cartStruct,
        ]);
    }

    public function cancelSubscription(int $licenseId, Context $context): void
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        try {
            $this->getClient()->post(sprintf(self::SBP_API_CANCEL_LICENSE, $licenseId), [
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

            throw $e;
        }
    }

    public function createRating(ReviewStruct $rating, Context $context): void
    {
    }

    public function getLicenses(Context $context): array
    {
        if ($this->authenticationProvider === null) {
            throw new \RuntimeException('App Store is not active');
        }

        $response = $this->getClient()->get(
            self::SBP_API_URL_LICENSES,
            [
                'query' => $this->storeService->getDefaultQueryParameters('en-GB'),
                'headers' => $this->getHeaders($this->authenticationProvider->getUserStoreToken($context)),
            ]
        );

        $body = json_decode($response->getBody()->getContents(), true);

        return [
            'headers' => $response->getHeaders(),
            'data' => $body,
        ];
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->storeService->createClient();
        }

        return $this->client;
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
        $headers = $this->getClient()->getConfig('headers');

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
