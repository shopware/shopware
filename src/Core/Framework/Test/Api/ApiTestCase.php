<?php declare(strict_types=1);

namespace Shopware\Framework\Test\Api;

use Doctrine\DBAL\Connection;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Shopware\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;

class ApiTestCase extends WebTestCase
{
    /**
     * @var Container
     */
    public $container;

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

    protected function setUp()
    {
        parent::setUp();

        self::bootKernel();

        $this->container = self::$kernel->getContainer();

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
            'HTTP_X_SW_APPLICATION_TOKEN' => 'TzhovH7sgws8n9UjgEdDEzNkA6xURua8',
            'HTTP_X_SW_CONTEXT_TOKEN' => Uuid::uuid4()->getHex(),
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);

        $this->apiClient = $apiClient;
        $this->storefrontApiClient = $storefrontApiClient;
    }

    public function tearDown()
    {
        self::$kernel->getContainer()->get(Connection::class)->executeQuery('DELETE FROM user WHERE username IN (:usernames)', ['usernames' => $this->apiUsernames], ['usernames' => Connection::PARAM_STR_ARRAY]);

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
        return $this->container;
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

        self::$kernel->getContainer()->get(Connection::class)->insert('user', [
            'id' => Uuid::uuid4()->getBytes(),
            'tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'name' => $username,
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
            'locale_id' => Uuid::fromStringToBytes('7b52d9dd-2b06-40ec-90be-9f57edf29be7'),
            'locale_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'active' => 1,
            'version_id' => Uuid::fromStringToBytes(Defaults::LIVE_VERSION),
            'locale_version_id' => Uuid::fromStringToBytes(Defaults::LIVE_VERSION),
        ]);

        $this->apiUsernames[] = $username;

        $authPayload = json_encode(['username' => $username, 'password' => $password]);

        $client->request('POST', '/api/v1/auth', [], [], [], $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('token', $data, 'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error'));

        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));
    }
}
