<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Storefront;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Guards the MCP server configuration in
 * src/Core/Framework/Resources/config/packages/mcp.php.
 *
 * This replaces a guard against hardcoded discovery `scan_dirs`, which mcp-bundle 0.12 removed along
 * with file-based discovery. The failure mode it protected against has an heir: a capability whose
 * namespace no server's `registry` patterns name is silently not registered, and a pattern naming
 * nothing is a fatal container error. Both are properties of this configuration file.
 *
 * @internal
 */
#[Package('framework')]
class McpServerRegistryConfigTest extends TestCase
{
    /**
     * Neither endpoint may claim the other's capabilities: the Admin API endpoint must not advertise
     * Store API tools, and vice versa. The bundle assigns a capability to every server whose patterns
     * match, so overlapping prefixes would put it on both.
     */
    public function testServerRegistriesDoNotOverlap(): void
    {
        $registries = $this->loadRegistries();

        static::assertArrayHasKey('admin', $registries);
        static::assertArrayHasKey('store_api', $registries);

        foreach ($this->patterns($registries['admin']) as $adminPattern) {
            foreach ($this->patterns($registries['store_api']) as $storeApiPattern) {
                static::assertFalse(
                    str_starts_with($adminPattern, $storeApiPattern) || str_starts_with($storeApiPattern, $adminPattern),
                    \sprintf('The MCP server patterns "%s" and "%s" overlap, so a capability would land on both endpoints.', $adminPattern, $storeApiPattern),
                );
            }
        }
    }

    /**
     * The wildcard would claim the other server's capabilities too, which is why plugin capabilities
     * are assigned by McpToolDiscoveryCompilerPass instead of by a catch-all pattern.
     */
    public function testNoServerUsesTheWildcard(): void
    {
        foreach ($this->loadRegistries() as $server => $registry) {
            static::assertNotContains('*', $this->patterns($registry), \sprintf('MCP server "%s" must not use the "*" pattern.', $server));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function capabilityNamespaceProvider(): iterable
    {
        yield 'core tools' => ['Shopware\\Core\\Framework\\Mcp\\Tool\\EntitySearchTool'];
        yield 'core prompts' => ['Shopware\\Core\\Framework\\Mcp\\Prompt\\ShopwareContextPrompt'];
        yield 'core resources' => ['Shopware\\Core\\Framework\\Mcp\\Resource\\EntityListResource'];
        yield 'storefront tools' => ['Shopware\\Storefront\\Mcp\\Tool\\ThemeConfigTool'];
        yield 'store api tools' => ['Shopware\\Core\\System\\SalesChannel\\Mcp\\Tool\\StoreApiContextTool'];
    }

    /**
     * Every in-tree capability namespace has to be claimed by exactly one server. A class matching
     * none is silently absent from every endpoint.
     */
    #[DataProvider('capabilityNamespaceProvider')]
    public function testInTreeCapabilityIsClaimedByExactlyOneServer(string $class): void
    {
        $matched = [];

        foreach ($this->loadRegistries() as $server => $registry) {
            foreach ($this->patterns($registry) as $pattern) {
                if (str_starts_with($class, $pattern)) {
                    $matched[] = $server;

                    break;
                }
            }
        }

        static::assertCount(1, $matched, \sprintf('"%s" is claimed by %d MCP servers, expected exactly 1.', $class, \count($matched)));
    }

    /**
     * @param array<mixed>|string $registry
     *
     * @return list<string>
     */
    private function patterns(array|string $registry): array
    {
        if (\is_string($registry)) {
            return [$registry];
        }

        // The registry is either one list covering every kind, or a map narrowing each kind.
        $patterns = array_is_list($registry) ? $registry : array_merge(...array_values($registry));

        return array_values(array_filter($patterns, 'is_string'));
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    private function loadRegistries(): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/srv/some/unrelated/project-root');
        $container->setParameter('kernel.bundles', ['Framework' => Framework::class, 'Storefront' => Storefront::class]);
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'mcp';
            }
        });

        $configDir = __DIR__ . '/../../../../../src/Core/Framework/Resources/config/packages';
        $loader = new PhpFileLoader($container, new FileLocator($configDir));
        $loader->load('mcp.php');

        $configs = $container->getExtensionConfig('mcp');
        static::assertArrayHasKey(0, $configs);
        static::assertArrayHasKey('servers', $configs[0]);

        $registries = [];
        foreach ($configs[0]['servers'] as $server => $config) {
            static::assertArrayHasKey('registry', $config, \sprintf('MCP server "%s" must declare a registry.', $server));
            $registries[$server] = $config['registry'];
        }

        return $registries;
    }
}
