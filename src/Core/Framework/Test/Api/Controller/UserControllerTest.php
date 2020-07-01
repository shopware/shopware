<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testMe(): void
    {
        $url = sprintf('/api/v%s/_info/me', PlatformRequest::API_VERSION);
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey('attributes', $content['data']);
        static::assertSame('user', $content['data']['type']);
        static::assertSame('admin@example.com', $content['data']['attributes']['email']);
        static::assertNotNull($content['data']['relationships']['avatarMedia']);
    }

    public function testCreateUser(): void
    {
        $client = $this->getBrowser();
        $data = [
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => 'password',
            'username' => 'foobar',
            'localeId' => $this->getContainer()->get(Connection::class)->fetchColumn('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ];

        $client->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/user', $data);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertArrayHasKey('errors', $content);
        static::assertEquals('This access token does not have the scope "user-verified" to process this Request', $content['errors'][0]['detail']);

        $this->getContainer()->get(Connection::class)
            ->executeUpdate("DELETE FROM user WHERE email = 'admin@example.com'");

        $this->kernelBrowser = null;
        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);
        $client->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/user', $data);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDeleteUser(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => 'password',
            'username' => 'foobar',
            'localeId' => $this->getContainer()->get(Connection::class)->fetchColumn('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ];

        $this->getContainer()->get('user.repository')
            ->create([$data], Context::createDefaultContext());

        $client = $this->getBrowser();
        $client->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/user/' . $id);
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertArrayHasKey('errors', $content);
        static::assertEquals('This access token does not have the scope "user-verified" to process this Request', $content['errors'][0]['detail']);

        $this->getContainer()->get(Connection::class)
            ->executeUpdate("DELETE FROM user WHERE email = 'admin@example.com'");

        $this->kernelBrowser = null;
        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);
        $client->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/user/' . $id);

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));
    }
}
