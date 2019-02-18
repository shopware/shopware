<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Framework;
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
     * @var ?string
     */
    private $host;

    public function __construct(Client $client, OpenSSLVerifier $openSSLVerifier, ?string $host)
    {
        $this->client = $client;
        $this->openSSLVerifier = $openSSLVerifier;
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
    public function getLicenseList(string $token): array
    {
        $response = $this->client->get(
            '/licenses',
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
        foreach ($data as $license) {
            $licenseStruct = new StoreLicenseStruct();
            $licenseStruct->setId($license['id']);
            $licenseStruct->setName($license['description']);
            $licenseStruct->setTechnicalPluginName($license['name']);
            $licenseStruct->setCreationDate(new \DateTime($license['creationDate']));
            if (isset($license['expirationDate'])) {
                $licenseStruct->setExpirationDate(new \DateTime($license['expirationDate']));
            }
            $licenseStruct->setAvailableVersion('');
            if (isset($license['priceModel']['type'])) {
                $type = new StoreLicenseTypeStruct($license['priceModel']['type']);
                $licenseStruct->setType($type);
            }
            if (isset($license['subscription']['expirationDate'])) {
                $subscription = new StoreLicenseSubscriptionStruct(new \DateTime($license['subscription']['expirationDate']));
                $licenseStruct->setSubscription($subscription);
            }
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
                'pluginName' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        $response = $this->client->post(
            '/pluginStore/updates', // old route
            [
                'query' => [
                    'shopwareVersion' => '5.5.1',
                    'domain' => $this->host,
                    'plugins' => [$pluginArray[0]['pluginName'] => $pluginArray[0]['version']],
                ],
                'body' => \json_encode([
                    'shopwareVersion' => '5.5.1',
                    'domain' => $this->host,
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
        foreach ($data as $update) {
            $updateList[] = new StoreUpdatesStruct($update['code'], $update['highestVersion'], $update['id'], $update['name']);
        }

        return $updateList;
    }

    private function getDefaultQueryParameters(): array
    {
        return [
            'shopwareVersion' => Framework::VERSION,
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

        if ($this->openSSLVerifier->isValid($response->getBody(), $signature)) {
            $response->getBody()->rewind();

            return;
        }

        throw new \RuntimeException('Signature not valid');
    }
}
