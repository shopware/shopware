<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Shopware\Core\Content\Flow\Api\FlowActionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Resource\FlowActionsResource;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlowActionsResource::class)]
class FlowActionsResourceTest extends TestCase
{
    public function testReturnsSortedActionsAsResource(): void
    {
        $context = Context::createDefaultContext();

        $action1 = new FlowActionDefinition('action.send-mail', ['order'], true);
        $action2 = new FlowActionDefinition('action.add-tag', ['entity'], false);
        $response = new FlowActionCollectorResponse([$action1, $action2]);

        $collector = $this->createMock(FlowActionCollector::class);
        $collector->method('collect')->with($context)->willReturn($response);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $resource = new FlowActionsResource($collector, $contextProvider);
        $result = ($resource)();

        static::assertSame('shopware://flow-actions', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $actions = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(2, $actions);
        static::assertSame('action.add-tag', $actions[0]['name']);
        static::assertSame(['entity'], $actions[0]['requirements']);
        static::assertFalse($actions[0]['delayable']);
        static::assertSame('action.send-mail', $actions[1]['name']);
        static::assertSame(['order'], $actions[1]['requirements']);
        static::assertTrue($actions[1]['delayable']);
    }
}
