<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\DualEra;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * A Store API tool whose result is above the inline limit of {@see McpToolResponse}. No shipped Store API
 * tool returns that much, so the large-result path of that endpoint needs its own.
 *
 * @internal
 */
#[Package('framework')]
final class OversizedResultTool extends McpToolResponse
{
    public const NAME = 'dual-era-test-oversized-result';

    public function __invoke(): McpToolResult
    {
        return $this->successResult(self::rows());
    }

    /**
     * 1000 rows of 120 characters, so the result is above the inline limit of 100 KB.
     *
     * @return list<array{text: string}>
     */
    public static function rows(): array
    {
        return array_fill(0, 1000, ['text' => str_repeat('x', 120)]);
    }

    public static function register(RegistryInterface $registry): void
    {
        $tool = new self();

        $registry->getTools();
        $registry->registerTool(
            new Tool(self::NAME, null, ['type' => 'object', 'properties' => new \stdClass()], 'Returns a result above the inline limit.', null),
            $tool(...),
        );
    }
}
