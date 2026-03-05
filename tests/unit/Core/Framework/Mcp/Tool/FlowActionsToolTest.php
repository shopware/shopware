<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Shopware\Core\Content\Flow\Api\FlowActionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\FlowActionsTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlowActionsTool::class)]
class FlowActionsToolTest extends TestCase
{
    public function testReturnsSortedActionsWithTotal(): void
    {
        $context = Context::createDefaultContext();

        $action1 = new FlowActionDefinition('action.send-mail', ['order'], true);
        $action2 = new FlowActionDefinition('action.add-tag', ['entity'], false);
        $response = new FlowActionCollectorResponse([$action1, $action2]);

        $collector = $this->createMock(FlowActionCollector::class);
        $collector->method('collect')->with($context)->willReturn($response);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new FlowActionsTool($collector, $contextProvider);
        $output = ($tool)();

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame(2, $data['_meta']['total']);
        static::assertCount(2, $data['data']);
        static::assertSame('action.add-tag', $data['data'][0]['name']);
        static::assertSame(['entity'], $data['data'][0]['requirements']);
        static::assertFalse($data['data'][0]['delayable']);
        static::assertSame('action.send-mail', $data['data'][1]['name']);
        static::assertSame(['order'], $data['data'][1]['requirements']);
        static::assertTrue($data['data'][1]['delayable']);
    }

    public function testReturnsEmptyActionsWhenNoneRegistered(): void
    {
        $context = Context::createDefaultContext();
        $response = new FlowActionCollectorResponse();

        $collector = $this->createMock(FlowActionCollector::class);
        $collector->method('collect')->with($context)->willReturn($response);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new FlowActionsTool($collector, $contextProvider);
        $output = ($tool)();

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame(0, $data['_meta']['total']);
        static::assertSame([], $data['data']);
    }
}
