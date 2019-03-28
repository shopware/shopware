<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Exception\StoreHostMissingException;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseSubscriptionStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseTypeStruct;
use Shopware\Core\Framework\Store\Struct\StoreUpdatesStruct;

final class StoreClient
{
    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';
    private const SHOPWARE_SHOP_SECRET_HEADER = 'X-Shopware-Shop-Secret';
    private const SHOPWARE_SIGNATURE_HEADER = 'X-Shopware-Signature';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var OpenSSLVerifier
     */
    private $openSSLVerifier;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $storeSettingsRepo;

    public function __construct(
        Client $client,
        OpenSSLVerifier $openSSLVerifier,
        EntityRepositoryInterface $pluginRepo,
        EntityRepositoryInterface $storeSettingsRepo
    ) {
        $this->client = $client;
        $this->openSSLVerifier = $openSSLVerifier;
        $this->pluginRepo = $pluginRepo;
        $this->storeSettingsRepo = $storeSettingsRepo;
    }

    public function ping(): void
    {
        $response = $this->client->get('/ping');
        $this->verifyResponseSignature($response);
    }

    public function loginWithShopwareId(string $shopwareId, string $password, string $language, Context $context): AccessTokenStruct
    {
        $response = $this->client->post(
            '/swplatform/login',
            [
                'body' => \json_encode([
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                    'shopwareUserId' => $context->getUserId(),
                ]),
                'query' => $this->getDefaultQueryParameters($language, $context),
            ]
        );
        $this->verifyResponseSignature($response);

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
        $shopSecret = $this->getShopSecret($context);

        $headers = [
            self::SHOPWARE_PLATFORM_TOKEN_HEADER => $storeToken,
        ];
        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        $response = $this->client->get(
            '/swplatform/pluginlicenses',
            [
                'query' => $this->getDefaultQueryParameters($language, $context),
                'headers' => array_merge(
                    $this->client->getConfig('headers'),
                    $headers
                ),
            ]
        );
        $this->verifyResponseSignature($response);

        $data = json_decode($response->getBody()->getContents(), true);

        $licenseList = [];
        $installedPlugins = [];

        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        /** @var PluginEntity $plugin */
        foreach ($pluginCollection as $plugin) {
            $installedPlugins[$plugin->getName()] = $plugin->getVersion();
        }

        foreach ($data['data'] as $license) {
            $licenseStruct = new StoreLicenseStruct();
            $licenseStruct->assign($license);

            $licenseStruct->setInstalled(array_key_exists($licenseStruct->getTechnicalPluginName(), $installedPlugins));
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
     * @return StoreUpdatesStruct[]
     */
    public function getUpdatesList(?string $storeToken, PluginCollection $pluginCollection, string $language, Context $context): array
    {
        $pluginArray = [];

        /** @var PluginEntity $plugin */
        foreach ($pluginCollection as $plugin) {
            $pluginArray[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        if (empty($pluginArray)) {
            return [];
        }

        $shopSecret = $this->getShopSecret($context);

        $headers = [];
        if ($storeToken) {
            $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER] = $storeToken;
        }
        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        $response = $this->client->post(
            '/swplatform/pluginupdates',
            [
                'query' => $this->getDefaultQueryParameters($language, $context),
                'body' => json_encode([
                    'plugins' => $pluginArray,
                ]),
                'headers' => array_merge(
                    $this->client->getConfig('headers'),
                    $headers
                ),
            ]
        );
        $this->verifyResponseSignature($response);

        $data = json_decode($response->getBody()->getContents(), true);

        $updateList = [];
        foreach ($data['data'] as $update) {
            $updateStruct = new StoreUpdatesStruct();
            $updateStruct->assign($update);
            $updateList[] = $updateStruct;
        }

        return $updateList;
    }

    public function getDownloadDataForPlugin(string $pluginName, string $storeToken, string $language, Context $context): PluginDownloadDataStruct
    {
        $shopSecret = $this->getShopSecret($context);

        $headers = [
            self::SHOPWARE_PLATFORM_TOKEN_HEADER => $storeToken,
        ];
        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        $response = $this->client->get(
            '/swplatform/pluginfiles/' . $pluginName,
            [
                'query' => $this->getDefaultQueryParameters($language, $context),
                'headers' => array_merge(
                    $this->client->getConfig('headers'),
                    $headers
                ),
            ]
        );
        $this->verifyResponseSignature($response);

        $dataStruct = new PluginDownloadDataStruct();
        $data = json_decode($response->getBody()->getContents(), true);
        $dataStruct->assign($data);

        return $dataStruct;
    }

    private function getDefaultQueryParameters(string $language, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', 'host'));

        $storeSettings = $this->storeSettingsRepo->search($criteria, $context)->first();
        if ($storeSettings === null) {
            throw new StoreHostMissingException();
        }

        return [
            'shopwareVersion' => Framework::VERSION,
            'language' => $language,
            'domain' => $storeSettings->getValue(),
        ];
    }

    private function getShopSecret(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', 'shopSecret'));

        $storeSettings = $this->storeSettingsRepo->search($criteria, $context)->first();

        if ($storeSettings === null) {
            return null;
        }

        return $storeSettings->getValue();
    }

    private function verifyResponseSignature(ResponseInterface $response): void
    {
        $signatureHeaderName = self::SHOPWARE_SIGNATURE_HEADER;
        $header = $response->getHeader($signatureHeaderName);
        if (!isset($header[0])) {
            throw new StoreSignatureValidationException(sprintf('Signature not found in header "%s"', $signatureHeaderName));
        }

        $signature = $header[0];

        if (empty($signature)) {
            throw new StoreSignatureValidationException(sprintf('Signature not found in header "%s"', $signatureHeaderName));
        }

        if (!$this->openSSLVerifier->isSystemSupported()) {
            return;
        }

        if ($this->openSSLVerifier->isValid($response->getBody()->getContents(), $signature)) {
            $response->getBody()->rewind();

            return;
        }

        throw new StoreSignatureValidationException('Signature not valid');
    }
}
