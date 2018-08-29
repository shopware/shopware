<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

trait AdminApiTestBehaviour
{
    /**
     * @var string[]
     */
    protected $apiUsernames = [];

    /**
     * @var Client|null
     */
    private $apiClient;

    /**
     * @after
     */
    public function resetAdminApiTestCaseTrait()
    {
        if (!$this->apiClient) {
            return;
        }

        $connection = $this->apiClient
            ->getContainer()
            ->get(Connection::class);

        try {
            $connection->executeQuery(
                'DELETE FROM user WHERE username IN (:usernames)',
                ['usernames' => $this->apiUsernames],
                ['usernames' => Connection::PARAM_STR_ARRAY]
            );
        } catch (\Exception $ex) {
            //nth
        }

        $this->apiUsernames = [];
        $this->apiClient = null;
    }

    public function createClient(
        KernelInterface $kernel = null,
        bool $enableReboot = false
    ): Client {
        if (!$kernel) {
            $kernel = KernelLifecycleManager::getKernel();
        }

        $apiClient = KernelLifecycleManager::createClient($kernel, $enableReboot);
        $apiClient->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);

        $this->authorizeClient($apiClient);

        return $this->apiClient = $apiClient;
    }

    public function assertEntityExists(Client $client, ...$params): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/' . implode('/', $params);

        $client->request('GET', $url);

        TestCase::assertSame(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            'Entity does not exists but should do.'
        );
    }

    public function assertEntityNotExists(Client $client, ...$params): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/' . implode('/', $params);

        $client->request('GET', $url);

        TestCase::assertSame(
            Response::HTTP_NOT_FOUND,
            $client->getResponse()->getStatusCode(),
            'Entity exists but should not.'
        );
    }

    /**
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidException
     * @throws \RuntimeException
     * @throws DBALException
     */
    public function authorizeClient(Client $client): void
    {
        $username = Uuid::uuid4()->getHex();
        $password = Uuid::uuid4()->getHex();

        /** @var Connection $connection */
        $connection = $client->getContainer()->get(Connection::class);
        $connection->insert('user', [
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

        if (!array_key_exists('access_token', $data)) {
            throw new \RuntimeException(
                'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true))
            );
        }

        if (!array_key_exists('refresh_token', $data)) {
            throw new \RuntimeException(
                $data, 'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
            );
        }

        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }

    protected function getClient(): Client
    {
        if ($this->apiClient) {
            return $this->apiClient;
        }

        return $this->apiClient = $this->createClient();
    }
}
