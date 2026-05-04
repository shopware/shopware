<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\AllowList;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
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

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'));

        $result = $provider->forCurrentRequest();
        static::assertSame(['shopware-entity-search', 'shopware-entity-schema'], $result['tools']);
        static::assertNull($result['resources']);
        static::assertNull($result['prompts']);
    }

    public function testToolsForCurrentRequestDelegatesToForCurrentRequest(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["tool-a"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'));

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

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'));

        $result = $provider->forCurrentRequest();
        static::assertNull($result['tools']);
        static::assertSame(['shopware://entities'], $result['resources']);
        static::assertSame(['shopware-context'], $result['prompts']);
    }

    public function testExpandsDirectDependenciesIntoToolAllowlist(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('{"tools":["shopware-entity-search"],"resources":null,"prompts":null}');

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'), [
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

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'), [
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

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'), [
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

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'));

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

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'));

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

        $provider = new McpAllowlistProvider($connection, $this->requestStackWithKey('test-key'));

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

    private function requestStackWithKey(string $accessKey): RequestStack
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $accessKey);

        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
