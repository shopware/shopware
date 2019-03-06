<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
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

    /**
     * @var ?string
     */
    private $host;

    public function __construct(Client $client, OpenSSLVerifier $openSSLVerifier, EntityRepositoryInterface $pluginRepo, ?string $host)
    {
        $this->client = $client;
        $this->openSSLVerifier = $openSSLVerifier;
        $this->pluginRepo = $pluginRepo;
        $this->host = $host;
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
                'query' => $this->getDefaultQueryParameters(),
                'headers' => [
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/json'],
                ],
            ]
        );
        $this->verifyResponseSignature($response);

        $data = json_decode($response->getBody()->getContents(), true);

        $accessTokenStruct = new AccessTokenStruct($data['token'], new \DateTime($data['expirationDate']));

        return $accessTokenStruct;
    }

    public function checkLogin(string $token): bool
    {
        $response = $this->client->get(
            '/accesstokens/' . $token,
            [
                'query' => $this->getDefaultQueryParameters(),
                'headers' => [
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/json'],
                ],
            ]
        );
        $this->verifyResponseSignature($response);

        return true;
    }

    /**
     * @throws \Exception
     *
     * @return array|StoreLicenseStruct[]
     */
    public function getLicenseList(string $token, Context $context): array
    {
        $response = $this->client->get(
            '/swplatform/pluginlicenses',
            [
                'query' => $this->getDefaultQueryParameters(),
                'headers' => [
                    'X-Shopware-Token' => $token,
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/vnd.api+json,application/json'],
                ],
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
            $installedPlugins[] = $plugin->getName();
        }

        foreach ($data['data'] as $license) {
            $licenseStruct = new StoreLicenseStruct();
            $licenseStruct->setId($license['id']);
            $licenseStruct->setName($license['name']);
            $licenseStruct->setTechnicalPluginName($license['technicalPluginName']);
            $licenseStruct->setCreationDate(new \DateTime($license['creationDate']));
            if (isset($license['expirationDate'])) {
                $licenseStruct->setExpirationDate(new \DateTime($license['expirationDate']));
            }
            if (isset($license['availableVersion'])) {
                $licenseStruct->setAvailableVersion($license['availableVersion']);
            }
            if (isset($license['type']['name'])) {
                $type = new StoreLicenseTypeStruct($license['type']['name']);
                $licenseStruct->setType($type);
            }
            if (isset($license['subscription']['expirationDate'])) {
                $subscription = new StoreLicenseSubscriptionStruct(new \DateTime($license['subscription']['expirationDate']));
                $licenseStruct->setSubscription($subscription);
            }
            $licenseStruct->setInstalled(in_array($licenseStruct->getTechnicalPluginName(), $installedPlugins, true));

            $licenseList[] = $licenseStruct;
        }

        return $licenseList;
    }

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
                'query' => $this->getDefaultQueryParameters(),
                'body' => \json_encode([
                    'plugins' => $pluginArray,
                ]),
                'headers' => [
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/vnd.api+json,application/json'],
                ],
            ]
        );
        $this->verifyResponseSignature($response);

        $data = json_decode($response->getBody()->getContents(), true);

        $updateList = [];
        foreach ($data['data'] as $update) {
            $updateList[] = new StoreUpdatesStruct($update['name'], $update['version'], $update['changelog'], new \DateTime($update['releaseDate']));
        }

        return $updateList;
    }

    public function getDownloadDataForPlugin(string $pluginName, string $token): array
    {
        $response = $this->client->get(
            '/swplatform/pluginfiles/' . $pluginName,
            [
                'query' => $this->getDefaultQueryParameters(),
                'headers' => [
                    'X-Shopware-Token' => $token,
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/vnd.api+json,application/json'],
                ],
            ]
        );
        $this->verifyResponseSignature($response);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    private function getDefaultQueryParameters(): array
    {
        return [
            'shopwareVersion' => '6.0.0',
            'language' => 'de_DE',
            'domain' => $this->host,
        ];
    }

    private function verifyResponseSignature(ResponseInterface $response): void
    {
        $signatureHeaderName = 'x-shopware-signature';
        $signature = $response->getHeader($signatureHeaderName)[0];

        if (empty($signature)) {
            throw new \RuntimeException(sprintf('Signature not found in header "%s"', $signatureHeaderName));
        }

        if (!$this->openSSLVerifier->isSystemSupported()) {
            return;
        }

        if ($this->openSSLVerifier->isValid($response->getBody()->getContents(), $signature)) {
            $response->getBody()->rewind();

            return;
        }

        throw new \RuntimeException('Signature not valid');
    }
}
