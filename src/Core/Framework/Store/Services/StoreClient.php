<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseSubscriptionStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseTypeStruct;

final class StoreClient
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
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

        $data = json_decode($response->getBody()->getContents(), true);

        $accessTokenStruct = new AccessTokenStruct($data['token'], new \DateTime($data['expirationDate']));

        return $accessTokenStruct;
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function checkLogin(string $token): bool
    {
        $this->client->get(
            '/accesstokens/' . $token,
            [
                'query' => $this->getDefaultQueryParameters(),
                'headers' => [
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/json'],
                ],
            ]
        );

        return true;
    }

    /**
     * @param string $token
     *
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

    private function getDefaultQueryParameters(): array
    {
        return [
            'shopwareVersion' => Framework::VERSION,
            'language' => 'de_DE',
            'domain' => 'fk2.test.shopware.in',
        ];
    }
}
