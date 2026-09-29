<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearchResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolSearchResult::class)]
class ToolSearchResultTest extends TestCase
{
    public function testStoresSearchResultData(): void
    {
        $tool = new Tool(
            name: 'shopware-product-search',
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: 'Search products',
            annotations: null,
        );

        $result = new ToolSearchResult($tool, 3.5, ['name:substring']);

        static::assertSame($tool, $result->tool);
        static::assertSame(3.5, $result->score);
        static::assertSame(['name:substring'], $result->matchedIn);
    }
}
