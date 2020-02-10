<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

trait AdminApiTestBehaviour
{
    /**
     * @var string[]
     */
    protected $apiUsernames = [];

    /**
     * @var string[]
     */
    protected $apiIntegrations = [];

    /**
     * @var KernelBrowser|null
     */
    private $kernelBrowser;

    /**
     * @var KernelBrowser|null
     */
    private $integrationBrowser;

    /**
     * @after
     */
    public function resetAdminApiTestCaseTrait(): void
    {
        if (!$this->kernelBrowser) {
            return;
        }

        $connection = $this->kernelBrowser
            ->getContainer()
            ->get(Connection::class);

        try {
            $connection->executeUpdate(
                'DELETE FROM user WHERE username IN (:usernames)',
                ['usernames' => $this->apiUsernames],
                ['usernames' => Connection::PARAM_STR_ARRAY]
            );
            $connection->executeUpdate(
                'DELETE FROM integration WHERE id IN (:ids)',
                ['ids' => $this->apiIntegrations],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        } catch (\Exception $ex) {
            //nth
        }

        $this->apiUsernames = [];
        $this->kernelBrowser = null;
    }

    public function createClient(
        ?KernelInterface $kernel = null,
        bool $enableReboot = false,
        bool $authorized = true
    ): KernelBrowser {
        if (!$kernel) {
            $kernel = $this->getKernel();
        }

        $apiBrowser = KernelLifecycleManager::createBrowser($kernel, $enableReboot);

        $apiBrowser->followRedirects();
        $apiBrowser->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
        ]);

        if ($authorized) {
            $this->authorizeBrowser($apiBrowser);
        }

        return $this->kernelBrowser = $apiBrowser;
    }

    public function assertEntityExists(KernelBrowser $browser, ...$params): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/' . implode('/', $params);

        $browser->request('GET', $url);

        TestCase::assertSame(
            Response::HTTP_OK,
            $browser->getResponse()->getStatusCode(),
            'Entity does not exists but should do. Response: ' . $browser->getResponse()->getContent()
        );
    }

    public function assertEntityNotExists(KernelBrowser $browser, ...$params): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/' . implode('/', $params);

        $browser->request('GET', $url);

        TestCase::assertSame(
            Response::HTTP_NOT_FOUND,
            $browser->getResponse()->getStatusCode(),
            'Entity exists but should not.'
        );
    }

    /**
     * @throws InvalidUuidException
     * @throws \RuntimeException
     * @throws DBALException
     */
    public function authorizeBrowser(KernelBrowser $browser): void
    {
        $username = Uuid::randomHex();
        $password = Uuid::randomHex();

        /** @var Connection $connection */
        $connection = $browser->getContainer()->get(Connection::class);
        $userId = Uuid::randomBytes();

        $connection->insert('user', [
            'id' => $userId,
            'first_name' => $username,
            'last_name' => '',
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'locale_id' => $this->getLocaleOfSystemLanguage($connection),
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'admin' => 1,
        ]);

        $this->apiUsernames[] = $username;

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $username,
            'password' => $password,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($browser->getResponse()->getContent(), true);

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

        $browser->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }

    /**
     * @throws InvalidUuidException
     * @throws \RuntimeException
     * @throws DBALException
     */
    public function authorizeBrowserWithIntegration(KernelBrowser $browser): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();
        $id = Uuid::randomBytes();

        /** @var Connection $connection */
        $connection = $browser->getContainer()->get(Connection::class);

        $connection->insert('integration', [
            'id' => $id,
            'write_access' => true,
            'access_key' => $accessKey,
            'secret_access_key' => password_hash($secretAccessKey, PASSWORD_BCRYPT),
            'label' => 'test integration',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->apiIntegrations[] = $id;

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretAccessKey,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($browser->getResponse()->getContent(), true);

        if (!array_key_exists('access_token', $data)) {
            throw new \RuntimeException(
                'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true))
            );
        }

        $browser->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }

    abstract protected function getKernel(): KernelInterface;

    protected function getBrowser(bool $authorized = true): KernelBrowser
    {
        if ($this->kernelBrowser) {
            return $this->kernelBrowser;
        }

        return $this->kernelBrowser = $this->createClient(
            null,
            false,
            $authorized
        );
    }

    protected function getBrowserAuthenticatedWithIntegration(): KernelBrowser
    {
        if ($this->integrationBrowser) {
            return $this->integrationBrowser;
        }

        $apiBrowser = KernelLifecycleManager::createBrowser($this->getKernel());

        $apiBrowser->followRedirects();
        $apiBrowser->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
        ]);

        $this->authorizeBrowserWithIntegration($apiBrowser);

        return $this->integrationBrowser = $apiBrowser;
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
