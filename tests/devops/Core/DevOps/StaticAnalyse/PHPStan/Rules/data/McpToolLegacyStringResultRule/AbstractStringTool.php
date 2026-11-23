<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

abstract class AbstractStringTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '{"success":true,"data":[]}';
    }
}
