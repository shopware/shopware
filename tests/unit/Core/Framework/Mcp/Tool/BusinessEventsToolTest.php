<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\BusinessEventsTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BusinessEventsTool::class)]
class BusinessEventsToolTest extends TestCase
{
    public function testReturnsEventsWithTotal(): void
    {
        $definition = new BusinessEventDefinition('test.event', TestEventClass::class, ['orderId' => 'string']);
        $response = new BusinessEventCollectorResponse([$definition]);

        $collector = $this->createMock(BusinessEventCollector::class);
        $collector->method('collect')->willReturn($response);

        $context = Context::createDefaultContext();
        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new BusinessEventsTool($collector, $contextProvider);
        $output = ($tool)();

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame(1, $data['_meta']['total']);
        static::assertCount(1, $data['data']);
        static::assertSame('test.event', $data['data'][0]['name']);
        static::assertSame(TestEventClass::class, $data['data'][0]['class']);
        static::assertSame(['orderId' => 'string'], $data['data'][0]['data']);
    }
}

/**
 * @internal
 */
class TestEventClass
{
}
