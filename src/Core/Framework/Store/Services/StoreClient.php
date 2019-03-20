<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseSubscriptionStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseTypeStruct;
use Shopware\Core\Framework\Store\Struct\StoreUpdatesStruct;

final class StoreClient
{
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

    public function __construct(Client $client, OpenSSLVerifier $openSSLVerifier, EntityRepositoryInterface $pluginRepo)
    {
        $this->client = $client;
        $this->openSSLVerifier = $openSSLVerifier;
        $this->pluginRepo = $pluginRepo;
    }

    public function loginWithShopwareId(string $shopwareId, string $password): AccessTokenStruct
    {
        $response = $this->client->post(
            '/swplatform/login',
            [
                'body' => \json_encode([
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                ]),
                'query' => array_merge(
                    $this->client->getConfig('query'),
                    $this->getDefaultQueryParameters()
                ),
            ]
        );
        $this->verifyResponseSignature($response);

        $data = json_decode($response->getBody()->getContents(), true);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($data);

        return $accessTokenStruct;
    }

    /**
     * @return StoreLicenseStruct[]
     */
    public function getLicenseList(string $token, Context $context): array
    {
        $response = $this->client->get(
            '/swplatform/pluginlicenses',
            [
                'query' => array_merge(
                    $this->client->getConfig('query'),
                    $this->getDefaultQueryParameters()
                ),
                'headers' => array_merge(
                    $this->client->getConfig('headers'),
                    ['X-Shopware-Token' => $token]
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
    public function getUpdatesList(PluginCollection $pluginCollection): array
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

        $response = $this->client->post(
            '/swplatform/pluginupdates',
            [
                'query' => array_merge(
                    $this->client->getConfig('query'),
                    $this->getDefaultQueryParameters()
                ),
                'body' => \json_encode([
                    'plugins' => $pluginArray,
                ]),
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

    public function getDownloadDataForPlugin(string $pluginName, string $token): PluginDownloadDataStruct
    {
        $response = $this->client->get(
            '/swplatform/pluginfiles/' . $pluginName,
            [
                'query' => array_merge(
                    $this->client->getConfig('query'),
                    $this->getDefaultQueryParameters()
                ),
                'headers' => array_merge(
                    $this->client->getConfig('headers'),
                    ['X-Shopware-Token' => $token]
                ),
            ]
        );
        $this->verifyResponseSignature($response);

        $dataStruct = new PluginDownloadDataStruct();
        $data = json_decode($response->getBody()->getContents(), true);
        $dataStruct->assign($data);

        return $dataStruct;
    }

    private function getDefaultQueryParameters(): array
    {
        return [
            'shopwareVersion' => Framework::VERSION,
            'language' => 'de_DE',
        ];
    }

    private function verifyResponseSignature(ResponseInterface $response): void
    {
        $signatureHeaderName = 'x-shopware-signature';
        $signature = $response->getHeader($signatureHeaderName)[0];

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
