<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
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
    public function resetAdminApiTestCaseTrait(): void
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
            $connection->executeQuery('DELETE FROM media');
        } catch (\Exception $ex) {
            //nth
        }

        $this->apiUsernames = [];
        $this->apiClient = null;
    }

    public function createClient(
        ?KernelInterface $kernel = null,
        bool $enableReboot = false
    ): Client {
        if (!$kernel) {
            $kernel = KernelLifecycleManager::getKernel();
        }

        $apiClient = KernelLifecycleManager::createClient($kernel, $enableReboot);
        $apiClient->followRedirects();
        $apiClient->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
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
            'Entity does not exists but should do. Response: ' . $client->getResponse()->getContent()
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
     * @throws InvalidUuidException
     * @throws \RuntimeException
     * @throws DBALException
     */
    public function authorizeClient(Client $client): void
    {
        $username = Uuid::randomHex();
        $password = Uuid::randomHex();

        /** @var Connection $connection */
        $connection = $client->getContainer()->get(Connection::class);
        $userId = Uuid::randomBytes();
        $avatarId = Uuid::randomBytes();

        $connection->insert('media', [
            'id' => $avatarId,
            'mime_type' => 'image/png',
            'file_size' => 1024,
            'uploaded_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('user', [
            'id' => $userId,
            'first_name' => $username,
            'last_name' => '',
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'locale_id' => $this->getLocaleOfSystemLanguage($connection),
            'active' => 1,
            'avatar_id' => $avatarId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
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
                'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
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

    private function getLocaleOfSystemLanguage(Connection $connection): string
    {
        $builder = $connection->createQueryBuilder();

        return (string) $builder->select('locale.id')
            ->from('language', 'language')
            ->innerJoin('language', 'locale', 'locale', 'language.locale_id = locale.id')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
            ->execute()
            ->fetchColumn();
    }
}
