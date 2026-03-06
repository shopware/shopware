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
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
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

    public function testDryRunReturnsAvailableTransitionsWithRequestedActionValid(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $transition = new StateMachineTransitionEntity();
        $transition->setActionName('process');
        $toState = new StateMachineStateEntity();
        $toState->setTechnicalName('in_progress');
        $transition->setToStateMachineState($toState);

        $registry = $this->createMock(StateMachineRegistry::class);
        $registry->method('getAvailableTransitions')->willReturn([$transition]);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new StateMachineTransitionTool($registry, $contextProvider);
        $output = ($tool)('order', 'id', 'process', 'stateId', true);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertTrue($data['data']['actionValid']);
        static::assertSame('process', $data['data']['requestedAction']);
        static::assertCount(1, $data['data']['availableTransitions']);
        static::assertSame('process', $data['data']['availableTransitions'][0]['actionName']);
        static::assertSame('in_progress', $data['data']['availableTransitions'][0]['toStateName']);
        static::assertTrue($data['_meta']['dryRun']);
    }

    public function testDryRunReturnsActionValidFalseWhenActionNotAvailable(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $transition = new StateMachineTransitionEntity();
        $transition->setActionName('cancel');
        $toState = new StateMachineStateEntity();
        $toState->setTechnicalName('cancelled');
        $transition->setToStateMachineState($toState);

        $registry = $this->createMock(StateMachineRegistry::class);
        $registry->method('getAvailableTransitions')->willReturn([$transition]);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new StateMachineTransitionTool($registry, $contextProvider);
        $output = ($tool)('order', 'id', 'process', 'stateId', true);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertFalse($data['data']['actionValid']);
        static::assertSame('process', $data['data']['requestedAction']);
    }

    public function testDryRunReturnsErrorWhenRegistryThrows(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(StateMachineRegistry::class);
        $registry->method('getAvailableTransitions')->willThrowException(new \RuntimeException('Entity not found'));

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new StateMachineTransitionTool($registry, $contextProvider);
        $output = ($tool)('order', 'id', 'process', 'stateId', true);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertSame('Entity not found', $data['error']);
    }

    public function testExecuteTransitionReturnsStatesWhenAllowed(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read', 'order:update']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier('state-id');
        $state->setTechnicalName('completed');
        $state->setName('Completed');

        $registry = $this->createMock(StateMachineRegistry::class);
        $registry->method('transition')->willReturn(new StateMachineStateCollection([$state]));

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new StateMachineTransitionTool($registry, $contextProvider);
        $output = ($tool)('order', 'id', 'complete', 'stateId', false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
        static::assertCount(1, $data['data']);
        static::assertSame('completed', $data['data'][0]['technicalName']);
        static::assertSame('Completed', $data['data'][0]['name']);
    }
}
