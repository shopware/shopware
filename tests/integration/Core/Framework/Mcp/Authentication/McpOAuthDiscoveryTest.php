<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpExceptionListener;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class McpOAuthDiscoveryTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testProtectedResourceMetadataNamesTheMcpEndpointAndTheAdminApiIssuer(): void
    {
        $document = $this->fetchJson('/.well-known/oauth-protected-resource/api/_mcp');

        static::assertSame([
            'resource' => 'http://localhost/api/_mcp',
            'authorization_servers' => ['http://localhost/api'],
            'bearer_methods_supported' => ['header'],
        ], $document);
    }

    public function testAuthorizationServerMetadataDescribesTheAuthorizationCodeFlow(): void
    {
        $document = $this->fetchJson('/.well-known/oauth-authorization-server/api');

        static::assertSame([
            'issuer' => 'http://localhost/api',
            'authorization_endpoint' => 'http://localhost/api/oauth/authorize',
            'token_endpoint' => 'http://localhost/api/oauth/token',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'authorization_response_iss_parameter_supported' => true,
        ], $document);
    }

    public function testDocumentsKeepTheBasePathOfAnInstallationInASubdirectory(): void
    {
        $server = ['SCRIPT_NAME' => '/shop/index.php', 'SCRIPT_FILENAME' => '/var/www/shop/index.php'];

        $resource = $this->fetchJson('/shop/.well-known/oauth-protected-resource/api/_mcp', $server);
        static::assertSame('http://localhost/shop/api/_mcp', $resource['resource']);
        static::assertSame(['http://localhost/shop/api'], $resource['authorization_servers']);

        $authorizationServer = $this->fetchJson('/shop/.well-known/oauth-authorization-server/api', $server);
        static::assertSame('http://localhost/shop/api', $authorizationServer['issuer']);
        static::assertSame('http://localhost/shop/api/oauth/token', $authorizationServer['token_endpoint']);
    }

    public function testUnknownHostFallsBackToAppUrl(): void
    {
        $document = $this->fetchJson('/.well-known/oauth-protected-resource/api/_mcp', ['HTTP_HOST' => 'attacker.example']);

        $appUrl = rtrim((string) EnvironmentHelper::getVariable('APP_URL'), '/');
        static::assertSame($appUrl . '/api/_mcp', $document['resource']);
        static::assertSame([$appUrl . '/api'], $document['authorization_servers']);
    }

    public function testUnauthenticatedMcpRequestPointsToTheProtectedResourceMetadata(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('POST', '/api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], '{"jsonrpc":"2.0","method":"initialize","id":1}');

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        static::assertSame(
            'Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource/api/_mcp"',
            $response->headers->get('WWW-Authenticate')
        );

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(McpExceptionListener::CODE_UNAUTHORIZED, $body['error']['code']);
    }

    public function testInvalidTokenOnTheMcpEndpointIsReportedAsInvalidToken(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('POST', '/api/_mcp', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer not-a-jwt',
        ], '{"jsonrpc":"2.0","method":"initialize","id":1}');

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        static::assertSame(
            'Bearer error="invalid_token", resource_metadata="http://localhost/.well-known/oauth-protected-resource/api/_mcp"',
            $response->headers->get('WWW-Authenticate')
        );
    }

    public function testOtherAdminApiRoutesSendNoMcpChallenge(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('GET', '/api/_info/me');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
        static::assertFalse($browser->getResponse()->headers->has('WWW-Authenticate'));
    }

    /**
     * @param array<string, string> $server
     *
     * @return array<string, mixed>
     */
    private function fetchJson(string $uri, array $server = []): array
    {
        $browser = $this->getBrowser(false);
        $browser->request('GET', $uri, [], [], $server);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));

        $document = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($document);

        return $document;
    }
}
