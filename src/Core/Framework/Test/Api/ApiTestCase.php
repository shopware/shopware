<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiTestCase extends WebTestCase
{
    /**
     * @var string[]
     */
    protected $apiUsernames = [];

    /**
     * @var Client
     */
    protected $apiClient;

    /**
     * @var Client
     */
    protected $storefrontApiClient;

    /**
     * @var array
     */
    protected $salesChannelIds = [];

    /**
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidException
     */
    protected function setUp()
    {
        parent::setUp();

        self::bootKernel();

        $apiClient = $this->getClient();
        $apiClient->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);
        $this->authorizeClient($apiClient);

        $storefrontApiClient = $this->getClient();
        $storefrontApiClient->setServerParameters([
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_Accept' => 'application/json',
            'HTTP_X_SW_CONTEXT_TOKEN' => Uuid::uuid4()->getHex(),
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);
        $this->authorizeStorefrontClient($storefrontApiClient);

        $this->apiClient = $apiClient;
        $this->storefrontApiClient = $storefrontApiClient;
    }

    public function tearDown()
    {
        try {
            self::$container->get(Connection::class)->executeQuery('DELETE FROM user WHERE username IN (:usernames)', ['usernames' => $this->apiUsernames], ['usernames' => Connection::PARAM_STR_ARRAY]);
            self::$container->get(Connection::class)->executeQuery('DELETE FROM sales_channel WHERE id IN (:salesChannelIds)', ['salesChannelIds' => $this->salesChannelIds], ['salesChannelIds' => Connection::PARAM_STR_ARRAY]);
        } catch (\Exception $ex) {
        }

        parent::tearDown();
    }

    public function getClient()
    {
        $clientKernel = self::createKernel();
        $clientKernel->boot();

        return $clientKernel->getContainer()->get('test.client');
    }

    public function getContainer()
    {
        return self::$container;
    }

    public function assertEntityExists(...$params): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/' . implode('/', $params);

        $this->apiClient->request('GET', $url);

        $this->assertSame(Response::HTTP_OK, $this->apiClient->getResponse()->getStatusCode(), 'Entity does not exists but should do.');
    }

    public function assertEntityNotExists(...$params): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/' . implode('/', $params);

        $this->apiClient->request('GET', $url);

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->apiClient->getResponse()->getStatusCode(), 'Entity exists but should not.');
    }

    /**
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidException
     */
    protected function authorizeClient(Client $client): void
    {
        $username = Uuid::uuid4()->getHex();
        $password = Uuid::uuid4()->getHex();

        self::$container->get(Connection::class)->insert('user', [
            'id' => Uuid::uuid4()->getBytes(),
            'tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'name' => $username,
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromStringToBytes(Defaults::LOCALE),
            'locale_version_id' => Uuid::fromStringToBytes(Defaults::LIVE_VERSION),
            'locale_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
        ]);

        $this->apiUsernames[] = $username;

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $username,
            'password' => $password,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $data, 'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true)));
        $this->assertArrayHasKey('refresh_token', $data, 'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error'));

        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }

    /**
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidException
     */
    protected function authorizeStorefrontClient(Client $storefrontApiClient): void
    {
        $salesChannelId = Uuid::uuid4();
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        $salesChannelRepository = self::$container->get('sales_channel.repository');

        $salesChannelRepository->upsert([[
            'id' => $salesChannelId->getHex(),
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT_API,
            'name' => 'API Test case sales channel',
            'accessKey' => $accessKey,
            'secretAccessKey' => $secretKey,
            'languageId' => Defaults::LANGUAGE,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'countryId' => Defaults::COUNTRY,
            'catalogIds' => [Defaults::CATALOG],
            'currencyIds' => [Defaults::CURRENCY],
            'languageIds' => [Defaults::LANGUAGE],
        ]], Context::createDefaultContext(Defaults::TENANT_ID));

        $this->salesChannelIds[] = $salesChannelId->getBytes();

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $storefrontApiClient->request('POST', '/storefront-api/oauth/token', $authPayload);

        $data = json_decode($storefrontApiClient->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $data, 'No token returned from API: ' . (($data['errors'][0]['detail'] ?? 'unknown error') . print_r($data, true)));

        $storefrontApiClient->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }
}
