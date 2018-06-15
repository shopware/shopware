<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
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
    protected $touchpoints = [];

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
            self::$container->get(Connection::class)->executeQuery('DELETE FROM touchpoint WHERE access_key IN (:accessKeys)', ['accessKeys' => $this->touchpoints], ['accessKeys' => Connection::PARAM_STR_ARRAY]);
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
            'locale_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'active' => 1,
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

    protected function authorizeStorefrontClient(Client $storefrontApiClient): void
    {
        $touchpoint = Uuid::uuid4();

        self::$container->get(Connection::class)->insert('touchpoint', [
            'id' => $touchpoint->getBytes(),
            'tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'name' => $touchpoint->getHex(),
            'type' => 'storefront_api',
            'access_key' => $touchpoint->getHex(),
            'secret_access_key' => password_hash(hash('sha512', $touchpoint->getHex()), PASSWORD_ARGON2I),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE),
            'language_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'currency_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'currency_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'payment_method_id' => Uuid::fromHexToBytes(Defaults::PAYMENT_METHOD_DEBIT),
            'payment_method_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'payment_method_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'shipping_method_id' => Uuid::fromHexToBytes(Defaults::SHIPPING_METHOD),
            'shipping_method_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'shipping_method_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'country_id' => Uuid::fromHexToBytes(Defaults::COUNTRY),
            'country_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'country_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'catalog_ids' => json_encode([Defaults::CATALOG]),
            'currency_ids' => json_encode([Defaults::CURRENCY]),
            'language_ids' => json_encode([Defaults::LANGUAGE]),
        ]);

        $this->touchpoints[] = $touchpoint->getHex();

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $touchpoint->getHex(),
            'client_secret' => $touchpoint->getHex(),
        ];

        $storefrontApiClient->request('POST', '/storefront-api/oauth/token', $authPayload);

        $data = json_decode($storefrontApiClient->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $data, 'No token returned from API: ' . (($data['errors'][0]['detail'] ?? 'unknown error') . print_r($data, true)));

        $storefrontApiClient->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }
}
