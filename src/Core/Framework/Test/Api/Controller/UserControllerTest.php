<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function tearDown(): void
    {
        $this->resetBrowser();
    }

    /**
     * @group slow
     */
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

    public function testRemoveRoleAssignment(): void
    {
        $ids = new IdsCollection();

        $user = [
            'id' => $ids->get('user'),
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => 'password',
            'username' => 'foobar',
            'localeId' => $this->getContainer()->get(Connection::class)->fetchColumn('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [
                ['id' => $ids->get('role-1'), 'name' => 'role-1'],
                ['id' => $ids->get('role-2'), 'name' => 'role-2'],
            ],
        ];

        $this->getContainer()->get('user.repository')
            ->create([$user], Context::createDefaultContext());

        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);
        $client->request('DELETE', '/api/v' . PlatformRequest::API_VERSION . '/user/' . $ids->get('user') . '/acl-roles/' . $ids->get('role-1'));

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));

        $assigned = $this->getContainer()->get(Connection::class)
            ->fetchAll(
                'SELECT LOWER(HEX(acl_role_id)) as id FROM acl_user_role WHERE user_id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('user'))]
            );

        $assigned = array_column($assigned, 'id');
        static::assertEquals(array_values($ids->getList(['role-2'])), $assigned);
    }

    public function testAddRoleAssignment(): void
    {
        $ids = new IdsCollection();

        $user = [
            'id' => $ids->get('user'),
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => 'password',
            'username' => 'foobar',
            'localeId' => $this->getContainer()->get(Connection::class)->fetchColumn('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [],
        ];

        $this->getContainer()->get('user.repository')
            ->create([$user], Context::createDefaultContext());

        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);
        $client->request(
            'PATCH',
            '/api/v' . PlatformRequest::API_VERSION . '/user/' . $ids->get('user'),
            [
                'aclRoles' => [
                    ['id' => $ids->get('role-1'), 'name' => 'role-1'],
                    ['id' => $ids->get('role-2'), 'name' => 'role-2'],
                ],
            ]
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));

        $assigned = $this->getContainer()->get(Connection::class)
            ->fetchAll(
                'SELECT LOWER(HEX(acl_role_id)) as id FROM acl_user_role WHERE user_id = :id ORDER BY acl_role_id ASC',
                ['id' => Uuid::fromHexToBytes($ids->get('user'))]
            );

        $assigned = array_column($assigned, 'id');
        $expectedIds = $ids->getList(['role-1', 'role-2']);
        sort($expectedIds);
        static::assertEquals(array_values($expectedIds), $assigned);
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

    public function testSetOwnProfileWithPermission(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['user_change_me']);
        $this->getBrowser()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/_info/me', ['firstName' => 'newName']);
        $responsePatch = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $responsePatch->getStatusCode(), $responsePatch->getContent());

        $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/_info/me');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertEquals('newName', json_decode($response->getContent(), true)['data']['attributes']['firstName']);
    }

    public function testSetOwnProfileNoPermission(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], []);
        $this->getBrowser()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/_info/me');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
        static::assertEquals(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($response->getContent(), true)['errors'][0]['code'], $response->getContent());
        static::assertEquals(['user_change_me'], json_decode(json_decode($response->getContent(), true)['errors'][0]['detail'], true)['missingPrivileges'], $response->getContent());
    }

    public function testSetOwnProfilePermissionButNotAllowedField(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['user_change_me']);
        $this->getBrowser()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/_info/me', ['title' => 'newTitle']);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
        static::assertEquals(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($response->getContent(), true)['errors'][0]['code'], $response->getContent());
        static::assertEquals(['user:update'], json_decode(json_decode($response->getContent(), true)['errors'][0]['detail'], true)['missingPrivileges'], $response->getContent());
    }
}
