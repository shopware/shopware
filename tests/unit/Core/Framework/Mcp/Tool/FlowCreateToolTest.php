<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\FlowCreateTool;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlowCreateTool::class)]
class FlowCreateToolTest extends TestCase
{
    public function testCreateSimpleFlowDryRun(): void
    {
        $tool = $this->createTool();
        $output = ($tool)(
            name: 'Tag new orders',
            eventName: 'checkout.order.placed',
            actionName: 'action.add.order.tag',
            actionConfig: '{"tagIds": {"abc123": "new-order"}}',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame(1, $data['_meta']['sequenceCount']);
        static::assertSame('Tag new orders', $data['data']['name']);
        static::assertSame('checkout.order.placed', $data['data']['eventName']);
        static::assertFalse($data['data']['active']);
        static::assertCount(1, $data['data']['sequences']);

        $sequence = $data['data']['sequences'][0];
        static::assertSame('action.add.order.tag', $sequence['actionName']);
        static::assertSame(['tagIds' => ['abc123' => 'new-order']], $sequence['config']);
        static::assertSame(1, $sequence['position']);
        static::assertFalse($sequence['trueCase']);
    }

    public function testCreateConditionalFlowDryRun(): void
    {
        $ruleId = Uuid::randomHex();

        $tool = $this->createTool();
        $output = ($tool)(
            name: 'VIP tag for high-value orders',
            eventName: 'checkout.order.placed',
            actionName: 'action.add.order.tag',
            actionConfig: '{"tagIds": {"def456": "vip"}}',
            ruleId: $ruleId,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame(2, $data['_meta']['sequenceCount']);
        static::assertCount(2, $data['data']['sequences']);

        $conditionSequence = $data['data']['sequences'][0];
        static::assertSame($ruleId, $conditionSequence['ruleId']);
        static::assertNull($conditionSequence['actionName']);
        static::assertSame([], $conditionSequence['config']);

        $actionSequence = $data['data']['sequences'][1];
        static::assertSame($conditionSequence['id'], $actionSequence['parentId']);
        static::assertNull($actionSequence['ruleId']);
        static::assertSame('action.add.order.tag', $actionSequence['actionName']);
        static::assertTrue($actionSequence['trueCase']);
    }

    public function testCreateFlowPersists(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('create');

        $tool = $this->createTool($repository);
        $output = ($tool)(
            name: 'Persisted flow',
            eventName: 'checkout.order.placed',
            actionName: 'action.add.order.tag',
            dryRun: false,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
        static::assertArrayHasKey('flowId', $data['data']);
        static::assertSame('Persisted flow', $data['data']['name']);
        static::assertSame('checkout.order.placed', $data['data']['eventName']);
        static::assertFalse($data['data']['active']);
    }

    public function testInvalidActionConfigReturnsError(): void
    {
        $tool = $this->createTool();
        $output = ($tool)(
            name: 'Bad config',
            eventName: 'checkout.order.placed',
            actionName: 'action.add.order.tag',
            actionConfig: '{invalid json}',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Invalid actionConfig JSON', $data['error']);
    }

    public function testMissingAclReturnsError(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('create');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new FlowCreateTool($repository, $contextProvider);
        $output = ($tool)(
            name: 'No access',
            eventName: 'checkout.order.placed',
            actionName: 'action.add.order.tag',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('flow:create', $data['error']);
    }

    public function testDefaultValues(): void
    {
        $tool = $this->createTool();
        $output = ($tool)(
            name: 'Defaults test',
            eventName: 'checkout.order.placed',
            actionName: 'action.stop.flow',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['data']['active']);
        static::assertSame(1, $data['data']['priority']);
        static::assertSame('', $data['data']['description']);
        static::assertSame([], $data['data']['sequences'][0]['config']);
    }

    private function createTool(?EntityRepository $repository = null): FlowCreateTool
    {
        $context = Context::createDefaultContext();

        $repository ??= $this->createMock(EntityRepository::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new FlowCreateTool($repository, $contextProvider);
    }
}
