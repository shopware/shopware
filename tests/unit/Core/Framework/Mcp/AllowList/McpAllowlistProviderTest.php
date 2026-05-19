<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\AllowList;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(McpAllowlistProvider::class)]
class McpAllowlistProviderTest extends TestCase
{
    public function testReturnsListedToolsWhenAllowlistIsSet(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["shopware-entity-search","shopware-entity-schema"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey());

        $result = $provider->forCurrentRequest();
        static::assertSame(['shopware-entity-search', 'shopware-entity-schema'], $result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testToolsForCurrentRequestDelegatesToForCurrentRequest(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["tool-a"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey());

        static::assertSame(['tool-a'], $provider->toolsForCurrentRequest());
        static::assertNull($provider->resourcesForCurrentRequest());
        static::assertNull($provider->promptsForCurrentRequest());
    }

    public function testResourcesAndPromptsAreFiltered(): void
    {
        $json = json_encode([
            'tools' => null,
            'resources' => ['shopware://entities'],
            'prompts' => ['shopware-context'],
        ], \JSON_THROW_ON_ERROR);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($json);

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey());

        $result = $provider->forCurrentRequest();
        static::assertNull($result['tools']);
        static::assertSame(['shopware://entities'], $result['resources']);
        static::assertSame(['shopware-context'], $result['prompts']);
    }

    public function testExpandsDirectDependenciesIntoToolAllowlist(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["shopware-entity-search"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey(), [
            'shopware-entity-search' => ['shopware-entity-schema'],
        ]);

        $result = $provider->forCurrentRequest();
        static::assertNotNull($result['tools']);
        static::assertContains('shopware-entity-search', $result['tools']);
        static::assertContains('shopware-entity-schema', $result['tools']);
    }

    public function testExpandsTransitiveDependencies(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["shopware-entity-delete"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey(), [
            'shopware-entity-delete' => ['shopware-entity-search'],
            'shopware-entity-search' => ['shopware-entity-schema'],
        ]);

        $result = $provider->forCurrentRequest();
        static::assertNotNull($result['tools']);
        static::assertContains('shopware-entity-delete', $result['tools']);
        static::assertContains('shopware-entity-search', $result['tools']);
        static::assertContains('shopware-entity-schema', $result['tools']);
    }

    public function testDoesNotDuplicateToolsAlreadyInAllowlist(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["shopware-entity-search","shopware-entity-schema"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey(), [
            'shopware-entity-search' => ['shopware-entity-schema'],
        ]);

        $result = $provider->forCurrentRequest();
        static::assertNotNull($result['tools']);
        static::assertSame(array_unique($result['tools']), $result['tools']);
        static::assertCount(2, $result['tools']);
    }

    public function testReturnsEmptyToolsArrayWhenToolsAllowlistIsEmptyJsonArray(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":[],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey());

        $result = $provider->forCurrentRequest();
        static::assertSame([], $result['tools']);
    }

    public function testReturnsUnrestrictedWhenNoRequest(): void
    {
        $provider = new McpAllowlistProvider(
            static::createStub(Connection::class),
            new RequestStack(),
        );

        $result = $provider->forCurrentRequest();
        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testReturnsUnrestrictedWhenNoAccessKey(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $provider = new McpAllowlistProvider(
            static::createStub(Connection::class),
            $requestStack,
        );

        $result = $provider->forCurrentRequest();
        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    /**
     * @return iterable<string, array{string|false}>
     */
    public static function unrestrictedDatabaseValueProvider(): iterable
    {
        yield 'DB column is null' => [false];
        yield 'DB column is empty string' => [''];
        yield 'invalid JSON' => ['{not-valid-json}'];
        yield 'JSON is not an array/object' => ['"just-a-string"'];
    }

    #[DataProvider('unrestrictedDatabaseValueProvider')]
    public function testReturnsUnrestrictedForNonArrayDbValue(string|false $dbValue): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($dbValue);

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey());

        $result = $provider->forCurrentRequest();
        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testForAccessKeyReturnsAllowlistForValidKey(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["shopware-entity-search","shopware-entity-schema"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, new RequestStack());

        $result = $provider->forAccessKey('SWIA-test');
        static::assertSame(['shopware-entity-search', 'shopware-entity-schema'], $result['tools']);
    }

    public function testForAccessKeyExpandsDependencies(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["shopware-entity-delete"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, new RequestStack(), [
            'shopware-entity-delete' => ['shopware-entity-search'],
        ]);

        $result = $provider->forAccessKey('SWIA-test');
        static::assertNotNull($result['tools']);
        static::assertContains('shopware-entity-delete', $result['tools']);
        static::assertContains('shopware-entity-search', $result['tools']);
    }

    public function testForAccessKeyReturnsUnrestrictedWhenKeyNotFound(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $provider = new McpAllowlistProvider($connection, new RequestStack());

        $result = $provider->forAccessKey('SWIA-unknown');
        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testReturnsNullForKeyWhenValueIsNonArrayNonNull(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":"not-an-array","resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey());

        $result = $provider->forCurrentRequest();
        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testForAccessKeyReturnsUnrestrictedForInvalidJson(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{not-valid-json}');

        $provider = new McpAllowlistProvider($connection, new RequestStack());

        $result = $provider->forAccessKey('SWIA-test');
        static::assertNull($result['tools']);
    }

    // --- Bearer JWT, client_credentials ---

    public function testBearerJwtClientCredentialsUsesIntegrationAllowlist(): void
    {
        // For client_credentials grants, SymfonyBearerTokenValidator sets ATTRIBUTE_OAUTH_CLIENT_ID
        // to the integration's access key (SWIA...) from the JWT aud claim. No ATTRIBUTE_OAUTH_USER_ID
        // is set. The result is identical to a direct integration access key request.
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturn('{"tools":["cc-tool"],"resources":null,"prompts":null}');
        $connection->expects($this->never())->method('fetchAssociative');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        // No ATTRIBUTE_OAUTH_USER_ID — distinguishes this from a password-grant JWT.

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        static::assertSame(['cc-tool'], $result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    // --- forUserId / bearer JWT ---

    public function testBearerJwtPasswordGrantUsesUserAllowlist(): void
    {
        $userId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '{"tools":["bearer-tool"],"resources":null,"prompts":null}',
            'admin' => false,
        ]);

        // 'administration' is the OAuth client_id for password-grant JWTs — not a valid SWIA/SWUA key.
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'administration');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID, $userId);

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        static::assertSame(['bearer-tool'], $result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testBearerJwtWithoutUserIdIsUnrestricted(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'administration');
        // No ATTRIBUTE_OAUTH_USER_ID set.

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testAdminUserAlwaysUnrestrictedRegardlessOfAllowlist(): void
    {
        $userId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '{"tools":["restricted-tool"],"resources":null,"prompts":null}',
            'admin' => true,
        ]);

        $result = (new McpAllowlistProvider($connection, new RequestStack()))->forUserId($userId);

        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testForUserIdReturnsUnrestrictedWhenUserNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $result = (new McpAllowlistProvider($connection, new RequestStack()))->forUserId(Uuid::randomHex());

        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testForUserIdReturnsUnrestrictedWhenAllowlistIsNull(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => null,
            'admin' => false,
        ]);

        $result = (new McpAllowlistProvider($connection, new RequestStack()))->forUserId(Uuid::randomHex());

        static::assertNull($result['tools']);
    }

    public function testForUserIdReturnsUnrestrictedForInvalidJson(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '{not-valid-json}',
            'admin' => false,
        ]);

        $result = (new McpAllowlistProvider($connection, new RequestStack()))->forUserId(Uuid::randomHex());

        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testForUserIdReturnsUnrestrictedWhenJsonIsNotArray(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '"just-a-string"',
            'admin' => false,
        ]);

        $result = (new McpAllowlistProvider($connection, new RequestStack()))->forUserId(Uuid::randomHex());

        static::assertNull($result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testUserAccessKeyPathLooksUpUserAndAppliesAllowlist(): void
    {
        $userId = Uuid::randomHex();
        $userIdBytes = Uuid::fromHexToBytes($userId);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($userIdBytes);
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '{"tools":["user-key-tool"],"resources":null,"prompts":null}',
            'admin' => false,
        ]);

        $result = (new McpAllowlistProvider($connection, $this->requestStackWithKey('SWUAtestuseraccesskey00')))->forCurrentRequest();

        static::assertSame(['user-key-tool'], $result['tools']);
    }

    public function testUserAccessKeyReturnsUnrestrictedWhenKeyNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects($this->never())->method('fetchAssociative');

        $result = (new McpAllowlistProvider($connection, $this->requestStackWithKey('SWUAtestuseraccesskey00')))->forCurrentRequest();

        static::assertNull($result['tools']);
    }

    // --- Copilot intersection ---

    public function testCopilotIntersectsIntegrationAndUserAllowlists(): void
    {
        $appUserId = Uuid::randomHex();
        $appUserIdBytes = Uuid::fromHexToBytes($appUserId);

        $connection = $this->createMock(Connection::class);
        // Integration allowlist: tools A + B
        $connection->method('fetchOne')
            ->willReturn('{"tools":["tool-a","tool-b"],"resources":null,"prompts":null}');
        // User allowlist: tools B + C
        $connection->method('fetchAssociative')
            ->willReturn([
                'mcp_allowlist' => '{"tools":["tool-b","tool-c"],"resources":null,"prompts":null}',
                'admin' => false,
            ]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, $appUserId);

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        // Intersection: only tool-b is in both allowlists.
        static::assertSame(['tool-b'], $result['tools']);
    }

    public function testCopilotIntersectionWithNullIntegrationAllowlistUsesUserAllowlist(): void
    {
        $appUserId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // Integration has no allowlist (null = unrestricted).
        $connection->method('fetchOne')->willReturn(false);
        // User allowlist restricts to tool-a only.
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '{"tools":["tool-a"],"resources":null,"prompts":null}',
            'admin' => false,
        ]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, $appUserId);

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        // null ∩ [tool-a] = [tool-a]
        static::assertSame(['tool-a'], $result['tools']);
    }

    public function testCopilotIntersectionWithNullUserAllowlistUsesIntegrationAllowlist(): void
    {
        $appUserId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // Integration restricts to tool-b.
        $connection->method('fetchOne')
            ->willReturn('{"tools":["tool-b"],"resources":null,"prompts":null}');
        // User has no allowlist (null = unrestricted).
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => null,
            'admin' => false,
        ]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, $appUserId);

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        // [tool-b] ∩ null = [tool-b]
        static::assertSame(['tool-b'], $result['tools']);
    }

    public function testCopilotIntersectionWithBothNullIsUnrestricted(): void
    {
        $appUserId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => null,
            'admin' => false,
        ]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, $appUserId);

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        // null ∩ null = null (unrestricted)
        static::assertNull($result['tools']);
    }

    public function testAppUserIdHeaderUuidIsPassedToUserLookup(): void
    {
        // The UUID from sw-app-user-id must be converted to bytes and passed to the user table
        // query exactly. A wrong UUID would resolve a different user's allowlist.
        $appUserId = Uuid::randomHex();
        $capturedParams = null;

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturn('{"tools":["tool-a","tool-b"],"resources":null,"prompts":null}');
        $connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return ['mcp_allowlist' => '{"tools":["tool-b"],"resources":null,"prompts":null}', 'admin' => false];
            });

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, $appUserId);

        $stack = new RequestStack();
        $stack->push($request);

        (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        static::assertNotNull($capturedParams);
        static::assertSame(Uuid::fromHexToBytes($appUserId), $capturedParams['id']);
    }

    public function testInvalidAppUserIdHeaderIsIgnoredAndIntegrationAllowlistApplies(): void
    {
        // sw-app-user-id must be a valid UUID hex string. An arbitrary string must be silently
        // ignored so the request falls back to the integration-only allowlist.
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturn('{"tools":["integration-tool"],"resources":null,"prompts":null}');
        $connection->expects($this->never())->method('fetchAssociative');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, 'not-a-valid-uuid');

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        static::assertSame(['integration-tool'], $result['tools']);
    }

    public function testCopilotIntersectionWithBothEmptyArrayIsEmpty(): void
    {
        $appUserId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturn('{"tools":[],"resources":null,"prompts":null}');
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '{"tools":[],"resources":null,"prompts":null}',
            'admin' => false,
        ]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, $appUserId);

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        static::assertSame([], $result['tools']);
    }

    public function testCopilotAdminUserBypassesIntersection(): void
    {
        $appUserId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // Integration restricts to tool-b only.
        $connection->method('fetchOne')
            ->willReturn('{"tools":["tool-b"],"resources":null,"prompts":null}');
        // User is admin — should bypass, giving null (unrestricted) for user side.
        $connection->method('fetchAssociative')->willReturn([
            'mcp_allowlist' => '{"tools":["tool-a"]}',
            'admin' => true,
        ]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'SWIAtestintegrationkey00');
        $request->headers->set(PlatformRequest::HEADER_APP_USER_ID, $appUserId);

        $stack = new RequestStack();
        $stack->push($request);

        $result = (new McpAllowlistProvider($connection, $stack))->forCurrentRequest();

        // [tool-b] ∩ null (admin bypass) = [tool-b]
        static::assertSame(['tool-b'], $result['tools']);
    }

    private function requestStackWithKey(string $accessKey = 'SWIAtestintegrationkey00'): RequestStack
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $accessKey);

        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
