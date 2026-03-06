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
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySearchTool::class)]
class EntitySearchToolTest extends TestCase
{
    public function testSearchWithDefaultCriteria(): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->createMock(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(25);

        $result = new EntitySearchResult(
            'product',
            0,
            new EntityCollection(),
            null,
            $criteria,
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->with('product')->willReturn($definition);
        $registry->method('getRepository')->with('product')->willReturn($repository);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($criteria);

        $encoder = $this->createMock(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder);
        $output = ($tool)('product');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(0, $data['_meta']['total']);
        static::assertSame([], $data['data']);
        static::assertSame(1, $data['_meta']['page']);
        static::assertSame(25, $data['_meta']['limit']);
    }

    public function testSearchWithPagination(): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->createMock(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(10);

        $result = new EntitySearchResult(
            'product',
            42,
            new EntityCollection(),
            null,
            $criteria,
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($criteria);

        $encoder = $this->createMock(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder);
        $output = ($tool)('product', '{"limit": 10, "page": 2}');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(42, $data['_meta']['total']);
        static::assertSame(2, $data['_meta']['page']);
        static::assertSame(10, $data['_meta']['limit']);
    }

    public function testTopLevelParamsMergeIntoCriteria(): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->createMock(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(5);

        $result = new EntitySearchResult(
            'product',
            3,
            new EntityCollection(),
            null,
            $criteria,
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->with(
                static::callback(function (array $payload): bool {
                    return $payload['limit'] === 5 && $payload['page'] === 2 && $payload['term'] === 'shirt';
                }),
                static::anything(),
                static::anything(),
                static::anything(),
            )
            ->willReturn($criteria);

        $encoder = $this->createMock(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder);
        $output = ($tool)('product', '{}', 5, 2, 'shirt');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
    }

    public function testDeniesAccessWithoutReadPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $this->createMock(RequestCriteriaBuilder::class), $contextProvider, $this->createMock(JsonEntityEncoder::class));
        $output = ($tool)('product');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('product:read', $data['error']);
    }
}
