<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Moves the MCP bundle's own debug command off "debug:mcp".
 *
 * Both commands are called "debug:mcp": Shopware's {@see \Shopware\Core\Framework\Mcp\Command\DebugMcpCommand}
 * and the one the bundle added in 0.12. Two services claiming one name means the console silently
 * resolves to whichever was registered last, and the bundle's would win. Shopware's command is the
 * one to keep — it prints the ACL privileges, tool groups, allowlists and toolsets that have no
 * upstream equivalent, and the MCP eval suite parses its table.
 *
 * The bundle's command keeps its own value (it lists the configured servers and clients, and the
 * capabilities left unassigned by the "registry" patterns), so it is renamed rather than removed and
 * stays reachable both directly and through "debug:mcp --native".
 */
#[Package('framework')]
class McpDebugCommandCompilerPass implements CompilerPassInterface
{
    public const NATIVE_COMMAND_NAME = 'debug:mcp:native';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.debug_command')) {
            return;
        }

        $definition = $container->getDefinition('mcp.debug_command');

        // AddConsoleCommandPass reads the name from the tag's "command" attribute in preference to
        // the #[AsCommand] attribute on the class, so re-tagging is enough.
        $definition->clearTag('console.command');
        $definition->addTag('console.command', ['command' => self::NATIVE_COMMAND_NAME]);
    }
}
