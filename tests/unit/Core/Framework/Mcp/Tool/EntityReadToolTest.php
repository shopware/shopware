<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\McpEntityIncludes;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityReadTool::class)]
#[CoversClass(McpEntityIncludes::class)]
class EntityReadToolTest extends TestCase
{
    public function testReturnsDataWhenEntityFound(): void
    {
        $context = Context::createDefaultContext();
        $entity = new ArrayEntity(['id' => 'prod-123', 'name' => 'Test Product']);
        $collection = new EntitySearchResult(
            'product',
            1,
            new EntityCollection([$entity]),
            null,
            new Criteria(['prod-123']),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($collection);

        $definition = $this->createMock(EntityDefinition::class);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->with('product')->willReturn($definition);
        $registry->method('getRepository')->with('product')->willReturn($repository);

        $readCriteria = new Criteria(['prod-123']);
        $readCriteria->setIncludes([]);
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($readCriteria);

        $encoder = $this->createMock(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn(['id' => 'prod-123', 'name' => 'Test Product']);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, $encoder);
        $output = ($tool)('product', 'prod-123');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertArrayHasKey('data', $data);
        static::assertArrayNotHasKey('error', $data);
        static::assertSame('prod-123', $data['data']['id']);
        static::assertSame('Test Product', $data['data']['name']);
    }

    public function testReturnsErrorWhenEntityNotFound(): void
    {
        $context = Context::createDefaultContext();
        $emptyCollection = new EntitySearchResult(
            'product',
            0,
            new EntityCollection(),
            null,
            new Criteria(['prod-missing']),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($emptyCollection);

        $definition = $this->createMock(EntityDefinition::class);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->with('product')->willReturn($definition);
        $registry->method('getRepository')->with('product')->willReturn($repository);

        $missingCriteria = new Criteria(['prod-missing']);
        $missingCriteria->setIncludes([]);
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($missingCriteria);

        $encoder = $this->createMock(JsonEntityEncoder::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, $encoder);
        $output = ($tool)('product', 'prod-missing');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertArrayNotHasKey('data', $data);
        static::assertSame('Entity "product" with ID "prod-missing" not found.', $data['error']);
    }

    public function testMalformedCriteriaJsonReturnsError(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityReadTool(
            $registry,
            $this->createMock(RequestCriteriaBuilder::class),
            $contextProvider,
            $this->createMock(JsonEntityEncoder::class),
        );
        $output = ($tool)('product', 'some-id', 'not-json');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Invalid JSON', $data['error']);
        static::assertStringContainsString('criteria', $data['error']);
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

        $tool = new EntityReadTool($registry, $this->createMock(RequestCriteriaBuilder::class), $contextProvider, $this->createMock(JsonEntityEncoder::class));
        $output = ($tool)('product', 'some-id');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('product:read', $data['error']);
    }
}
