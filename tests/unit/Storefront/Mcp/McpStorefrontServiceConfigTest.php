<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Mcp;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Guards that Storefront MCP services are correctly registered and tagged.
 *
 * The tag must be `mcp.tool` (not `shopware.mcp.tool`). Non-Core bundle tools
 * are not processed by McpToolCompilerPass, so they must use the SDK tag directly.
 * Wrong tags cause silent disappearance from the MCP tool registry.
 *
 * The mcp.yaml scan_dirs must include `src/Storefront/Mcp` so the MCP SDK's
 * attribute discoverer finds the #[McpTool] attribute on each class.
 *
 * @internal
 */
#[CoversNothing]
#[Package('framework')]
class McpStorefrontServiceConfigTest extends TestCase
{
    public function testThemeConfigToolIsRegistered(): void
    {
        $container = $this->buildContainer();

        static::assertTrue(
            $container->hasDefinition(ThemeConfigTool::class),
            'ThemeConfigTool is not registered in Storefront mcp.xml',
        );
    }

    public function testThemeConfigToolIsTaggedWithMcpTool(): void
    {
        $container = $this->buildContainer();
        $definition = $container->getDefinition(ThemeConfigTool::class);

        static::assertTrue(
            $definition->hasTag('mcp.tool'),
            'ThemeConfigTool must be tagged "mcp.tool" (not "shopware.mcp.tool") — non-Core bundle tools are not processed by McpToolCompilerPass',
        );
    }

    public function testThemeConfigToolIsGatedBehindFeatureFlag(): void
    {
        $container = $this->buildContainer();
        $definition = $container->getDefinition(ThemeConfigTool::class);

        static::assertTrue(
            $definition->hasTag('shopware.feature'),
            'ThemeConfigTool must be tagged with shopware.feature flag MCP_SERVER',
        );

        $tags = $definition->getTag('shopware.feature');
        $flags = array_column($tags, 'flag');
        static::assertContains('MCP_SERVER', $flags, 'ThemeConfigTool shopware.feature tag must have flag=MCP_SERVER');
    }

    public function testMcpYamlIncludesStorefrontScanDir(): void
    {
        $yamlPath = __DIR__ . '/../../../../src/Core/Framework/Resources/config/packages/mcp.yaml';
        static::assertFileExists($yamlPath);

        $content = file_get_contents($yamlPath);
        static::assertNotFalse($content);
        static::assertStringContainsString(
            'src/Storefront/Mcp',
            $content,
            'mcp.yaml scan_dirs must include src/Storefront/Mcp so the MCP SDK discovers #[McpTool] attributes on Storefront tools',
        );
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../src/Storefront/DependencyInjection/mcp.xml');

        return $container;
    }
}
