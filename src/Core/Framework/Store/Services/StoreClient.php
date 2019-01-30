<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
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

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function loginWithShopwareId(string $shopwareId, string $password): AccessTokenStruct
    {
        $response = $this->client->post(
            '/accesstokens',
            [
                'body' => \json_encode([
                    'shopwareId' => $shopwareId,
                    'password' => $password
                ]),
                'headers' => [
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/json']
                ]
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $accessTokenStruct = new AccessTokenStruct($data['token'], new \DateTime($data['expire']['date']), $data['userId']);

        return $accessTokenStruct;
    }

    /**
     * @param string $token
     *
     * @return array|StoreLicenseStruct[]
     */
    public function getLicenseList(string $token): array
    {
        $response = $this->client->get(
            '/licenses',
            [
                'query' => [
                    'shopwareVersion' => Framework::VERSION,
                    'domain' => 'fk2.test.shopware.in',
                ],
                'headers' => [
                    'X-Shopware-Token' => $token,
                    'Content-type' => 'application/json',
                    'ACCEPT' => ['application/vnd.api+json,application/json']
                ]
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
}
