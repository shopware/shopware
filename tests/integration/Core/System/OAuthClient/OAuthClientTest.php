<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\OAuthClient;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class OAuthClientTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testCrudValidatesRedirectUrisAndUsesDedicatedPermissions(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();
        $browser->setServerParameter('CONTENT_TYPE', 'application/json');
        $payload = ['id' => $id, 'name' => 'Desktop tool', 'redirectUris' => ['https://example.com/callback']];
        $browser->request('POST', '/api/oauth-client', content: json_encode($payload, \JSON_THROW_ON_ERROR));
        static::assertSame(204, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $browser->request('PATCH', '/api/oauth-client/' . $id, content: json_encode(['redirectUris' => ['https://example.com', 'http://example.com']], \JSON_THROW_ON_ERROR));
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        $errors = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertStringEndsWith('/redirectUris/1', $errors['errors'][0]['source']['pointer']);

        $viewer = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['oauth_client:read']);
        $viewer->authorizeBrowser($browser);
        $browser->request('GET', '/api/oauth-client/' . $id);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $browser->request('PATCH', '/api/oauth-client/' . $id, content: '{"active":false}');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->request('DELETE', '/api/oauth-client/' . $id);
        static::assertSame(403, $browser->getResponse()->getStatusCode());

        $integrationManager = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['integration:create', 'integration:read', 'integration:update']);
        $integrationManager->authorizeBrowser($browser);
        $browser->request('POST', '/api/oauth-client', content: json_encode([...$payload, 'id' => Uuid::randomHex()], \JSON_THROW_ON_ERROR));
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    #[DataProvider('unavailableStates')]
    public function testDatabaseClientLoginRefreshAndBearerLifecycle(bool $delete): void
    {
        $id = Uuid::randomHex();
        $clientId = 'oauth-' . $id;
        $repository = static::getContainer()->get('oauth_client.repository');
        $repository->create([['id' => $id, 'name' => 'Desktop tool', 'redirectUris' => ['http://127.0.0.1/callback']]], Context::createDefaultContext());
        $verifier = bin2hex(random_bytes(32));
        $query = [
            'response_type' => 'code', 'client_id' => $clientId,
            'redirect_uri' => 'http://127.0.0.1:54321/callback', 'state' => 'test-state', 'scope' => 'write',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
        $browser = $this->getBrowser(false);
        $browser->setServerParameter('CONTENT_TYPE', 'application/json');
        $user = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['product:read']);
        $user->authorizeBrowser($browser);
        $browser->request('GET', '/api/oauth/authorize/info', $query);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $info = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['id' => $clientId, 'name' => 'Desktop tool'], $info['client']);
        $browser->request('POST', '/api/oauth/authorize', content: json_encode([...$query, 'approved' => true], \JSON_THROW_ON_ERROR));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $decision = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        parse_str((string) parse_url($decision['redirectUri'], \PHP_URL_QUERY), $callback);
        static::assertSame('test-state', $callback['state']);

        $browser->request('POST', '/api/oauth/token', content: json_encode([
            'grant_type' => 'authorization_code', 'client_id' => $clientId,
            'code' => $callback['code'], 'code_verifier' => $verifier, 'redirect_uri' => $query['redirect_uri'],
        ], \JSON_THROW_ON_ERROR));
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $tokens = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $browser->setServerParameter('HTTP_Authorization', 'Bearer ' . $tokens['access_token']);
        $browser->request('GET', '/api/_info/me');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/product');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/tax');
        static::assertSame(403, $browser->getResponse()->getStatusCode());

        $refresh = ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']];
        $browser->request('POST', '/api/oauth/token', content: json_encode($refresh, \JSON_THROW_ON_ERROR));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $tokens = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $refresh['refresh_token'] = $tokens['refresh_token'];
        $browser->setServerParameter('HTTP_Authorization', 'Bearer ' . $tokens['access_token']);

        if ($delete) {
            $repository->delete([['id' => $id]], Context::createDefaultContext());
        } else {
            $repository->update([['id' => $id, 'active' => false]], Context::createDefaultContext());
        }

        $browser->request('GET', '/api/_info/me');
        static::assertSame(401, $browser->getResponse()->getStatusCode());
        $browser->request('POST', '/api/oauth/token', content: json_encode($refresh, \JSON_THROW_ON_ERROR));
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/oauth/authorize', $query);
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        static::assertFalse($browser->getResponse()->headers->has('Location'));

        if (!$delete) {
            $repository->update([['id' => $id, 'active' => true]], Context::createDefaultContext());
            $browser->request('GET', '/api/_info/me');
            static::assertSame(200, $browser->getResponse()->getStatusCode());
            $browser->request('POST', '/api/oauth/token', content: json_encode($refresh, \JSON_THROW_ON_ERROR));
            static::assertSame(200, $browser->getResponse()->getStatusCode());
        }
    }

    #[DataProvider('unavailableStates')]
    public function testUnavailableClientCannotExchangePendingCode(bool $delete): void
    {
        $id = Uuid::randomHex();
        $clientId = 'oauth-' . $id;
        $repository = static::getContainer()->get('oauth_client.repository');
        $repository->create([['id' => $id, 'name' => 'Desktop tool', 'redirectUris' => ['https://example.com/callback']]], Context::createDefaultContext());
        $verifier = bin2hex(random_bytes(32));
        $browser = $this->getBrowser();
        $browser->setServerParameter('CONTENT_TYPE', 'application/json');
        $browser->request('POST', '/api/oauth/token', content: json_encode([
            'grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => 'not-a-secret',
        ], \JSON_THROW_ON_ERROR));
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        $errors = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('14', $errors['errors'][0]['code']);

        $browser->request('POST', '/api/oauth/authorize', content: json_encode([
            'response_type' => 'code', 'client_id' => $clientId, 'approved' => true,
            'redirect_uri' => 'https://example.com/callback', 'state' => 'test-state', 'scope' => 'write',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], \JSON_THROW_ON_ERROR));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $decision = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        parse_str((string) parse_url($decision['redirectUri'], \PHP_URL_QUERY), $callback);

        if ($delete) {
            $repository->delete([['id' => $id]], Context::createDefaultContext());
        } else {
            $repository->update([['id' => $id, 'active' => false]], Context::createDefaultContext());
        }

        $browser->request('POST', '/api/oauth/token', content: json_encode([
            'grant_type' => 'authorization_code', 'client_id' => $clientId,
            'code' => $callback['code'], 'code_verifier' => $verifier, 'redirect_uri' => 'https://example.com/callback',
        ], \JSON_THROW_ON_ERROR));
        static::assertSame(401, $browser->getResponse()->getStatusCode());
        static::assertJson((string) $browser->getResponse()->getContent());
    }

    public function testDedicatedManagementRoleCanCreateUpdateAndDelete(): void
    {
        $browser = $this->getBrowser(false);
        $browser->setServerParameter('CONTENT_TYPE', 'application/json');
        $manager = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), [
            'oauth_client:create', 'oauth_client:read', 'oauth_client:update', 'oauth_client:delete',
        ]);
        $manager->authorizeBrowser($browser);
        $id = Uuid::randomHex();
        $browser->request('POST', '/api/oauth-client', content: json_encode([
            'id' => $id, 'name' => 'Desktop tool', 'redirectUris' => ['https://example.com/callback'],
        ], \JSON_THROW_ON_ERROR));
        static::assertSame(204, $browser->getResponse()->getStatusCode());
        $browser->request('PATCH', '/api/oauth-client/' . $id, content: '{"active":false}');
        static::assertSame(204, $browser->getResponse()->getStatusCode());
        $browser->request('DELETE', '/api/oauth-client/' . $id);
        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function unavailableStates(): \Generator
    {
        yield 'disabled application' => [false];
        yield 'deleted application' => [true];
    }
}
