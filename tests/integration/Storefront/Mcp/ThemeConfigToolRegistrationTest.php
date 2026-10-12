<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Mcp;

use Mcp\Capability\RegistryInterface;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Checks the theme config tool as the admin MCP server publishes it.
 *
 * @internal
 */
#[Package('discovery')]
class ThemeConfigToolRegistrationTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * Clients only see the input schema, so every parameter needs a description for a model to call the tool
     * correctly.
     */
    public function testEveryParameterIsDescribedInThePublishedInputSchema(): void
    {
        // the registry is filled while the server is built, so the server is resolved first
        static::assertInstanceOf(Server::class, static::getContainer()->get('mcp.server.admin'));
        $registry = static::getContainer()->get('mcp.server.admin.registry');
        static::assertInstanceOf(RegistryInterface::class, $registry);

        $properties = $registry->getTool('shopware-theme-config')->tool->inputSchema['properties'] ?? [];
        static::assertIsArray($properties);
        static::assertSame(['salesChannelId', 'action', 'config', 'dryRun'], array_keys($properties));

        foreach ($properties as $name => $property) {
            static::assertIsArray($property);
            static::assertIsString($property['description'] ?? null, \sprintf('$%s has no description', $name));
            static::assertNotSame('', $property['description'], \sprintf('$%s has an empty description', $name));
        }
    }
}
