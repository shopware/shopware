<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Mcp;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;
use Shopware\Tests\DevOps\Core\Framework\Mcp\McpDiscoveryScanDirsConfigTest;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Guards that Storefront MCP services are correctly registered and tagged.
 *
 * The tag must be `mcp.tool` (not `shopware.mcp.tool`). Non-Core bundle tools
 * are not processed by McpToolCompilerPass, so they must use the SDK tag directly.
 * Wrong tags cause silent disappearance from the MCP tool registry.
 *
 * Discovery of the Storefront `Mcp` scan dir (so the SDK finds the #[McpTool]
 * attributes) is covered by {@see McpDiscoveryScanDirsConfigTest}.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class McpStorefrontServiceConfigTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new PhpFileLoader($this->container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../src/Storefront/DependencyInjection/mcp.php');
    }

    public function testThemeConfigToolIsRegistered(): void
    {
        static::assertTrue(
            $this->container->hasDefinition(ThemeConfigTool::class),
            'ThemeConfigTool is not registered in Storefront mcp.php',
        );
    }

    public function testThemeConfigToolIsTaggedWithMcpTool(): void
    {
        static::assertTrue(
            $this->container->getDefinition(ThemeConfigTool::class)->hasTag('mcp.tool'),
            'ThemeConfigTool must be tagged "mcp.tool" (not "shopware.mcp.tool"). Non-Core bundle tools are not processed by McpToolCompilerPass',
        );
    }
}
