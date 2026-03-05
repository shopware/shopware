<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\StateMachineTransitionTool;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StateMachineTransitionTool::class)]
class StateMachineTransitionToolTest extends TestCase
{
    public function testDeniesReadWithoutReadPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(StateMachineRegistry::class);
        $registry->expects($this->never())->method('getAvailableTransitions');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new StateMachineTransitionTool($registry, $contextProvider);
        $output = ($tool)('order', 'some-id', 'process');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('order:read', $data['error']);
    }

    public function testDeniesTransitionWithoutUpdatePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(StateMachineRegistry::class);
        $registry->expects($this->never())->method('transition');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new StateMachineTransitionTool($registry, $contextProvider);
        $output = ($tool)('order', 'some-id', 'process', 'stateId', false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('order:update', $data['error']);
    }
}
