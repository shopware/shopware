<?php declare(strict_types=1);

namespace Shopware\Rest\Test;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Struct\Uuid;
use Shopware\Defaults;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\DependencyInjection\Container;

class ApiTestCase extends WebTestCase
{
    /**
     * @var Client
     */
    public static $client;

    /**
     * @var Container
     */
    public static $container;

    /**
     * @var string[]
     */
    protected static $apiUsernames = [];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $client = self::createClient(
            ['test_case' => 'ApiTest'],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            ]
        );

        self::$container = self::$kernel->getContainer();
        self::$client = self::authorizeClient($client);
    }

    public static function tearDownAfterClass()
    {
        self::$container->get(Connection::class)->executeQuery('DELETE FROM user WHERE username IN (:usernames)', ['usernames' => self::$apiUsernames], ['usernames' => Connection::PARAM_STR_ARRAY]);

        parent::tearDownAfterClass();
    }

    public function getClient()
    {
        return clone self::$client;
    }

    public function getContainer()
    {
        return self::$container;
    }

    private static function authorizeClient(Client $client): Client
    {
        $username = Uuid::uuid4()->getHex();
        $password = Uuid::uuid4()->getHex();

        self::$container->get(Connection::class)->insert('user', [
            'id' => Uuid::uuid4()->getBytes(),
            'name' => $username,
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
            'locale_id' => Uuid::fromStringToBytes('7b52d9dd-2b06-40ec-90be-9f57edf29be7'),
            'user_role_id' => '123',
            'active' => 1,
            'version_id' => Uuid::fromStringToBytes(Defaults::LIVE_VERSION),
            'locale_version_id' => Uuid::fromStringToBytes(Defaults::LIVE_VERSION),
        ]);

        self::$apiUsernames[] = $username;

        $authPayload = json_encode(['username' => $username, 'password' => $password]);

        $client->request('POST', '/api/auth', [], [], [], $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);

        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));

        return $client;
    }
}
