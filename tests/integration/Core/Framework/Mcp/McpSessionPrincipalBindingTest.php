<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Mcp\Server\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * Handshake-era MCP sessions are bound to the principal that sent `initialize`
 * (https://github.com/shopware/shopware/issues/20867#issuecomment-6058641213).
 *
 * @internal
 */
#[Package('framework')]
class McpSessionPrincipalBindingTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    private const UNKNOWN_SESSION_ID = '00000000-0000-4000-8000-000000000000';

    public function testIntegrationUsingAnAdministratorsSessionGetsTheUnknownSessionAnswer(): void
    {
        $admin = $this->getBrowser();
        $sessionId = $this->initialize($admin);

        $integration = $this->getBrowserAuthenticatedWithIntegration();

        $this->toolsList($integration, self::UNKNOWN_SESSION_ID);
        $unknown = $integration->getResponse();
        static::assertSame(404, $unknown->getStatusCode());

        $this->toolsList($integration, $sessionId);
        $foreign = $integration->getResponse();
        static::assertSame(404, $foreign->getStatusCode());
        static::assertSame($unknown->getContent(), $foreign->getContent());
        static::assertSame($unknown->headers->get('Content-Type'), $foreign->headers->get('Content-Type'));
        static::assertNull($foreign->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));

        $this->toolsList($admin, $sessionId);
        static::assertSame(200, $admin->getResponse()->getStatusCode(), (string) $admin->getResponse()->getContent());
    }

    public function testDeleteFromAnotherPrincipalKeepsTheSession(): void
    {
        $admin = $this->getBrowser();
        $sessionId = $this->initialize($admin);

        $integration = $this->getBrowserAuthenticatedWithIntegration();
        $integration->request('DELETE', '/api/_mcp', [], [], ['HTTP_MCP_SESSION_ID' => $sessionId]);
        static::assertSame(404, $integration->getResponse()->getStatusCode());

        $this->toolsList($admin, $sessionId);
        static::assertSame(200, $admin->getResponse()->getStatusCode(), (string) $admin->getResponse()->getContent());
    }

    public function testUserSessionSurvivesANewToken(): void
    {
        $admin = $this->getBrowser();
        $sessionId = $this->initialize($admin);
        $previousToken = $admin->getServerParameter('HTTP_Authorization');

        $username = $this->connection()->fetchOne('SELECT username FROM user WHERE email = :email', ['email' => 'admin@example.com']);
        static::assertIsString($username);
        $this->requestToken($admin, [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $username,
            'password' => 'shopware',
        ]);
        static::assertNotSame($previousToken, $admin->getServerParameter('HTTP_Authorization'));

        $this->toolsList($admin, $sessionId);
        static::assertSame(200, $admin->getResponse()->getStatusCode(), (string) $admin->getResponse()->getContent());
    }

    public function testIntegrationSessionSurvivesANewToken(): void
    {
        $integration = $this->getBrowserAuthenticatedWithIntegration();
        $sessionId = $this->initialize($integration);
        $previousToken = $integration->getServerParameter('HTTP_Authorization');

        $accessKey = $this->connection()->fetchOne(
            'SELECT access_key FROM integration WHERE id = :id',
            ['id' => $integration->getServerParameter('_integration_id')],
        );
        static::assertIsString($accessKey);
        $this->requestToken($integration, [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => 'shopware',
        ]);
        static::assertNotSame($previousToken, $integration->getServerParameter('HTTP_Authorization'));

        $this->toolsList($integration, $sessionId);
        static::assertSame(200, $integration->getResponse()->getStatusCode(), (string) $integration->getResponse()->getContent());
    }

    public function testSessionWithoutStoredPrincipalIsRejected(): void
    {
        $admin = $this->getBrowser();
        $sessionId = $this->initialize($admin);

        $store = static::getContainer()->get('mcp.server.admin.session.store');
        static::assertInstanceOf(SessionStoreInterface::class, $store);
        $uuid = Uuid::fromString($sessionId);
        $raw = $store->read($uuid);
        static::assertIsString($raw);
        $data = Json::decodeToArray($raw);
        static::assertArrayHasKey('shopware_principal', $data);
        unset($data['shopware_principal']);
        $store->write($uuid, Json::encode($data));

        $this->toolsList($admin, $sessionId);
        static::assertSame(404, $admin->getResponse()->getStatusCode());
    }

    private function initialize(KernelBrowser $browser): string
    {
        $browser->request('POST', '/api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], Json::encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-session-binding-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ]));

        $sessionId = $browser->getResponse()->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID);
        static::assertIsString($sessionId, 'initialize did not return an MCP session id: ' . $browser->getResponse()->getContent());

        return $sessionId;
    }

    private function toolsList(KernelBrowser $browser, string $sessionId): void
    {
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_MCP_SESSION_ID' => $sessionId],
            Json::encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'params' => new \stdClass(), 'id' => 2]),
        );
    }

    /**
     * @param array<string, string> $payload
     */
    private function requestToken(KernelBrowser $browser, array $payload): void
    {
        $browser->request('POST', '/api/oauth/token', [], [], ['CONTENT_TYPE' => 'application/json'], Json::encode($payload));

        $token = Json::decodeToArray((string) $browser->getResponse()->getContent())['access_token'] ?? null;
        static::assertIsString($token, 'no access token: ' . $browser->getResponse()->getContent());

        $browser->setServerParameter('HTTP_Authorization', 'Bearer ' . $token);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
