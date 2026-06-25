<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityAggregateTool::class)]
class EntityAggregateToolTest extends TestCase
{
    public function testReturnsAggregationsInDataKey(): void
    {
        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection();
        $aggregations->add(new CountResult('total', 42));

        $result = new EntitySearchResult('order', 0, new EntityCollection(), $aggregations, new Criteria(), $context);

        [$tool] = $this->createTool($context, $result);
        $output = ($tool)('order', '[{"type":"count","name":"total","field":"id"}]');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertArrayHasKey('aggregations', $data['data']);
        static::assertSame(42, $data['data']['aggregations']['total']['count']);
        static::assertArrayNotHasKey('_meta', $data);
    }

    public function testMultipleAggregationTypesAreSerializedCorrectly(): void
    {
        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection();
        $aggregations->add(new CountResult('total', 100));
        $aggregations->add(new AvgResult('avgValue', 99.5));

        $result = new EntitySearchResult('order', 0, new EntityCollection(), $aggregations, new Criteria(), $context);

        [$tool] = $this->createTool($context, $result);
        $output = ($tool)('order', '[{"type":"count","name":"total","field":"id"},{"type":"avg","name":"avgValue","field":"amountTotal"}]');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(100, $data['data']['aggregations']['total']['count']);
        static::assertSame(99.5, $data['data']['aggregations']['avgValue']['avg']);
    }

    public function testAggregationResultHasNoNameOrExtensionsKeys(): void
    {
        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection();
        $aggregations->add(new AvgResult('myAvg', 55.5));

        $result = new EntitySearchResult('order', 0, new EntityCollection(), $aggregations, new Criteria(), $context);

        [$tool] = $this->createTool($context, $result);
        $output = ($tool)('order', '[{"type":"avg","name":"myAvg","field":"amountTotal"}]');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('name', $data['data']['aggregations']['myAvg']);
        static::assertArrayNotHasKey('extensions', $data['data']['aggregations']['myAvg']);
        static::assertArrayNotHasKey('apiAlias', $data['data']['aggregations']['myAvg']);
    }

    public function testCriteriaBuilderReceivesAggregationsAndSearchUsesLimitZero(): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->createMock(EntityDefinition::class);

        $capturedPayload = null;
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload): Criteria {
                $capturedPayload = $payload;

                return new Criteria();
            });

        $capturedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$capturedCriteria, $context): EntitySearchResult {
                $capturedCriteria = $criteria;

                return new EntitySearchResult('order', 0, new EntityCollection(), null, new Criteria(), $context);
            });

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityAggregateTool($registry, $criteriaBuilder, $contextProvider);
        ($tool)('order', '[{"type":"count","name":"total","field":"id"}]');

        static::assertIsArray($capturedPayload);
        static::assertArrayNotHasKey('limit', $capturedPayload);
        static::assertCount(1, $capturedPayload['aggregations']);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(0, $capturedCriteria->getLimit());
    }

    public function testFiltersArePassedToCriteria(): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->createMock(EntityDefinition::class);

        $capturedPayload = null;
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload): Criteria {
                $capturedPayload = $payload;

                return new Criteria();
            });

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(
            new EntitySearchResult('order', 0, new EntityCollection(), null, new Criteria(), $context)
        );

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityAggregateTool($registry, $criteriaBuilder, $contextProvider);
        ($tool)(
            'order',
            '[{"type":"count","name":"total","field":"id"}]',
            '[{"type":"equals","field":"active","value":true}]',
        );

        static::assertIsArray($capturedPayload);
        static::assertArrayHasKey('filter', $capturedPayload);
        static::assertCount(1, $capturedPayload['filter']);
    }

    public function testEmptyFiltersAreNotPassedToCriteria(): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->createMock(EntityDefinition::class);

        $capturedPayload = null;
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload): Criteria {
                $capturedPayload = $payload;

                return new Criteria();
            });

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(
            new EntitySearchResult('order', 0, new EntityCollection(), null, new Criteria(), $context)
        );

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityAggregateTool($registry, $criteriaBuilder, $contextProvider);
        ($tool)('order', '[{"type":"count","name":"total","field":"id"}]');

        static::assertIsArray($capturedPayload);
        static::assertArrayNotHasKey('filter', $capturedPayload);
    }

    public function testReturnsErrorWhenEntityNotFound(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityAggregateTool($registry, $this->createMock(RequestCriteriaBuilder::class), $contextProvider);
        $data = json_decode(($tool)('unknown_entity', '[]'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('unknown_entity', $data['error']);
    }

    public function testReturnsErrorForNonListAggregations(): void
    {
        $context = Context::createDefaultContext();

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($this->createMock(EntityDefinition::class));
        $registry->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityAggregateTool($registry, $this->createMock(RequestCriteriaBuilder::class), $contextProvider);
        $output = ($tool)('order', '{"type":"count"}');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('aggregations must be a JSON array', $data['error']);
    }

    public function testMalformedFiltersJsonReturnsError(): void
    {
        $context = Context::createDefaultContext();

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($this->createMock(EntityDefinition::class));
        $registry->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityAggregateTool($registry, $this->createMock(RequestCriteriaBuilder::class), $contextProvider);
        $output = ($tool)('order', '[{"type":"count","name":"total","field":"id"}]', 'not-json');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Invalid JSON', $data['error']);
        static::assertStringContainsString('filters', $data['error']);
    }

    public function testMalformedAggregationsJsonReturnsError(): void
    {
        $context = Context::createDefaultContext();

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($this->createMock(EntityDefinition::class));
        $registry->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityAggregateTool($registry, $this->createMock(RequestCriteriaBuilder::class), $contextProvider);
        $output = ($tool)('order', 'not-json');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Invalid JSON', $data['error']);
        static::assertStringContainsString('aggregations', $data['error']);
    }

    public function testDeniesAccessWithoutReadPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityAggregateTool($registry, $this->createMock(RequestCriteriaBuilder::class), $contextProvider);
        $output = ($tool)('order', '[{"type":"count","name":"total","field":"id"}]');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('order:read', $data['error']);
    }

    /**
     * @param EntitySearchResult<*> $result
     *
     * @return array{EntityAggregateTool, EntityRepository<*>}
     */
    private function createTool(Context $context, EntitySearchResult $result): array
    {
        $definition = $this->createMock(EntityDefinition::class);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn(new Criteria());

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return [new EntityAggregateTool($registry, $criteriaBuilder, $contextProvider), $repository];
    }
}
