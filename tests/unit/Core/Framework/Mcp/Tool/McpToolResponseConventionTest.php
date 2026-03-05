<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResponse::class)]
class McpToolResponseConventionTest extends TestCase
{
    public function testAllMcpToolsUseResponseTrait(): void
    {
        $toolDir = \dirname(__DIR__, 6) . '/src/Core/Framework/Mcp/Tool';

        $finder = (new Finder())
            ->files()
            ->in($toolDir)
            ->name('*Tool.php')
            ->notName('McpToolResponse.php');

        $violations = [];

        foreach ($finder as $file) {
            $className = 'Shopware\\Core\\Framework\\Mcp\\Tool\\' . $file->getBasename('.php');

            if (!class_exists($className)) {
                continue;
            }

            $ref = new \ReflectionClass($className);

            $hasMcpToolAttribute = $ref->getAttributes(McpTool::class) !== [];

            if (!$hasMcpToolAttribute) {
                continue;
            }

            $usesTrait = \in_array(McpToolResponse::class, $ref->getTraitNames(), true);

            if (!$usesTrait) {
                $violations[] = $className;
            }
        }

        static::assertSame(
            [],
            $violations,
            \sprintf(
                "The following MCP tools do not use the McpToolResponse trait:\n- %s",
                implode("\n- ", $violations)
            )
        );
    }
}
