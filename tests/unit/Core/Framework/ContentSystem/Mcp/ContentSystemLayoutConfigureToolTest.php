<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Mcp\ContentSystemLayoutConfigureTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutConfigureTool::class)]
class ContentSystemLayoutConfigureToolTest extends TestCase
{
    public function testConfiguresNestedElementPropertiesAndStyle(): void
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());
        $tool = new ContentSystemLayoutConfigureTool($contextProvider);

        $layout = [[
            'id' => 'container',
            'component' => 'Sw:Grid:Container',
            'slots' => [
                'content' => [[
                    'id' => 'text',
                    'component' => 'Sw:Content:Text',
                    'properties' => ['text' => '<p>Old</p>'],
                ]],
            ],
        ]];

        $output = $tool(
            json_encode($layout, \JSON_THROW_ON_ERROR),
            'text',
            '{"text":"<h2>Welcome to Summer!</h2>"}',
            '{"justify-self":{"xs":"center"}}',
        );
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(
            '<h2>Welcome to Summer!</h2>',
            $data['data']['layout'][0]['slots']['content'][0]['properties']['text'],
        );
        static::assertSame(
            'center',
            $data['data']['layout'][0]['slots']['content'][0]['style']['justify-self']['xs'],
        );
    }

    public function testReturnsErrorForUnknownElement(): void
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());
        $tool = new ContentSystemLayoutConfigureTool($contextProvider);

        $output = $tool('[]', 'missing');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertSame('Element "missing" was not found in the draft layout.', $data['error']);
    }
}
