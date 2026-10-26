<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Schema\Enum\CacheScope;
use Mcp\Server\Wire\CachePolicy;
use Mcp\Server\Wire\Rev2026Codec;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Log\Package;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Runs the `cache` options of src/Core/Framework/Resources/config/packages/mcp.php through the real MCP
 * bundle extension and checks the cache policy it gives each server builder (SEP-2549 cache hints).
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class McpCachePolicyConfigTest extends TestCase
{
    private const LIST_METHODS = ['tools/list', 'prompts/list', 'resources/list', 'resources/templates/list'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function serverProvider(): iterable
    {
        yield 'Admin API server' => ['admin'];
        yield 'Store API server' => ['store_api'];
    }

    /**
     * Both servers answer per caller: the allowlist and `?toolsets=` shape the lists, and the resources are
     * read with the caller's credentials. No answer may be marked for a shared cache.
     */
    #[DataProvider('serverProvider')]
    public function testNoCacheableMethodIsPublic(string $server): void
    {
        $policy = $this->policy($server);

        foreach (Rev2026Codec::CACHEABLE_METHODS as $method) {
            static::assertSame(CacheScope::Private, $policy->scopeFor($method), $method);
        }
    }

    /**
     * The lists stay fresh for a minute, the fallback for clients that do not follow list changes.
     */
    #[DataProvider('serverProvider')]
    public function testListsAreFreshForAMinute(string $server): void
    {
        $policy = $this->policy($server);

        foreach (self::LIST_METHODS as $method) {
            static::assertSame(60_000, $policy->ttlFor($method), $method);
        }
    }

    /**
     * `resources/read` also reads stored tool results and app resources, so it is not cached by default;
     * the reference resources name their own TTL.
     */
    #[DataProvider('serverProvider')]
    public function testEverythingElseIsNotCached(string $server): void
    {
        $policy = $this->policy($server);

        static::assertSame(0, $policy->ttlFor('resources/read'));
        static::assertSame(0, $policy->ttlFor('server/discover'));
    }

    private function policy(string $server): CachePolicy
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/srv/some/unrelated/project-root');
        $container->setParameter('kernel.cache_dir', '/srv/some/unrelated/project-root/var/cache');
        $container->setParameter('kernel.build_dir', '/srv/some/unrelated/project-root/var/cache');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.bundles', ['Framework' => Framework::class]);

        $extension = (new McpBundle())->getContainerExtension();
        static::assertNotNull($extension);
        $container->registerExtension($extension);

        $configDir = __DIR__ . '/../../../../../src/Core/Framework/Resources/config/packages';
        (new PhpFileLoader($container, new FileLocator($configDir)))->load('mcp.php');
        $extension->load($container->getExtensionConfig('mcp'), $container);

        foreach ($container->getDefinition(\sprintf('mcp.server.%s.builder', $server))->getMethodCalls() as [$method, $arguments]) {
            if ($method === 'setCachePolicy') {
                return self::instantiate($arguments[0]);
            }
        }

        static::fail(\sprintf('The builder of MCP server "%s" gets no cache policy.', $server));
    }

    /**
     * Builds the policy from the chain of factory definitions the bundle registers:
     * `CachePolicy::default()`, then one `withMethod()` per override.
     */
    private static function instantiate(mixed $definition): CachePolicy
    {
        static::assertInstanceOf(Definition::class, $definition);
        $factory = $definition->getFactory();
        static::assertIsArray($factory);
        $arguments = $definition->getArguments();

        if ($factory === [CachePolicy::class, 'default']) {
            static::assertIsInt($arguments[0]);

            return CachePolicy::default($arguments[0], self::scope($arguments[1]));
        }

        static::assertSame('withMethod', $factory[1]);
        static::assertIsString($arguments[0]);
        static::assertIsInt($arguments[1]);

        return self::instantiate($factory[0])->withMethod($arguments[0], $arguments[1], self::scope($arguments[2]));
    }

    private static function scope(mixed $definition): CacheScope
    {
        static::assertInstanceOf(Definition::class, $definition);
        static::assertSame([CacheScope::class, 'from'], $definition->getFactory());
        static::assertIsString($definition->getArgument(0));

        return CacheScope::from($definition->getArgument(0));
    }
}
