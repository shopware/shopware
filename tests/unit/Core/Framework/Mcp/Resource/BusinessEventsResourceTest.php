<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Resource\BusinessEventsResource;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BusinessEventsResource::class)]
class BusinessEventsResourceTest extends TestCase
{
    public function testReturnsEventsAsResource(): void
    {
        $definition = new BusinessEventDefinition('test.event', TestResourceEventClass::class, ['orderId' => 'string']);
        $response = new BusinessEventCollectorResponse([$definition]);

        $collector = $this->createMock(BusinessEventCollector::class);
        $collector->method('collect')->willReturn($response);

        $context = Context::createDefaultContext();
        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $resource = new BusinessEventsResource($collector, $contextProvider);
        $result = ($resource)();

        static::assertSame('shopware://business-events', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $events = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $events);
        static::assertSame('test.event', $events[0]['name']);
        static::assertSame(TestResourceEventClass::class, $events[0]['class']);
        static::assertSame(['orderId' => 'string'], $events[0]['data']);
    }
}

/**
 * @internal
 */
class TestResourceEventClass
{
}
