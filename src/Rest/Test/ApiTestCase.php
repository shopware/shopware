<?php declare(strict_types=1);

namespace Shopware\Rest\Test;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
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

        $client = self::createClient(['test_case' => 'ApiTest'], ['CONTENT_TYPE' => 'application/json']);

        self::$container = self::$kernel->getContainer();
        self::$client = self::authorizeClient($client);
    }

    public static function tearDownAfterClass()
    {
        self::$container->get('dbal_connection')->executeQuery('DELETE FROM user WHERE username IN (:usernames)', ['usernames' => self::$apiUsernames], ['usernames' => Connection::PARAM_STR_ARRAY]);

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
        $username = Uuid::uuid4()->toString();
        $password = Uuid::uuid4()->toString();

        self::$container->get('dbal_connection')->insert('user', [
            'id' => Uuid::uuid4()->toString(),
            'name' => $username,
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
            'locale_id' => 'SWAG-LOCALE-ID-1',
            'user_role_id' => '123',
            'active' => 1,
        ]);

        self::$apiUsernames[] = $username;

        $authPayload = json_encode(['username' => $username, 'password' => $password]);

        $client->request('POST', '/api/auth', [], [], [], $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);

        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));

        return $client;
    }
}
