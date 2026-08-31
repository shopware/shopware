<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
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
use Shopware\Core\Framework\Mcp\Tool\McpEntityIncludes;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySearchTool::class)]
#[CoversClass(McpEntityIncludes::class)]
class EntitySearchToolTest extends TestCase
{
    public function testSearchWithDefaultCriteria(): void
    {
        $context = Context::createDefaultContext();
        $definition = static::createStub(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(25);
        $criteria->setIncludes([]);

        $result = new EntitySearchResult(
            'product',
            0,
            new EntityCollection(),
            null,
            $criteria,
            $context,
        );

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($criteria);

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
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
        $definition = static::createStub(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(10);
        $criteria->setIncludes([]);

        $result = new EntitySearchResult(
            'product',
            42,
            new EntityCollection(),
            null,
            $criteria,
            $context,
        );

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($criteria);

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
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
        $definition = static::createStub(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(5);
        $criteria->setIncludes([]);

        $result = new EntitySearchResult(
            'product',
            3,
            new EntityCollection(),
            null,
            $criteria,
            $context,
        );

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
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

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
        $output = ($tool)('product', '{}', 5, 2, 'shirt');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
    }

    public function testDefaultLimitIsAlwaysAppliedToPayload(): void
    {
        $context = Context::createDefaultContext();
        $definition = static::createStub(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(25);
        $criteria->setIncludes([]);

        $result = new EntitySearchResult('product', 0, new EntityCollection(), null, $criteria, $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->with(
                static::callback(function (array $payload): bool {
                    return isset($payload['limit']) && $payload['limit'] === 25;
                }),
                static::anything(),
                static::anything(),
                static::anything(),
            )
            ->willReturn($criteria);

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
        ($tool)('product');
    }

    public function testCriteriaJsonLimitTakesPrecedenceOverDefault(): void
    {
        $context = Context::createDefaultContext();
        $definition = static::createStub(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(50);
        $criteria->setIncludes([]);

        $result = new EntitySearchResult('product', 0, new EntityCollection(), null, $criteria, $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->with(
                static::callback(function (array $payload): bool {
                    return $payload['limit'] === 50;
                }),
                static::anything(),
                static::anything(),
                static::anything(),
            )
            ->willReturn($criteria);

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
        ($tool)('product', '{"limit": 50}');
    }

    public function testDefaultsTotalCountModeToExact(): void
    {
        $context = Context::createDefaultContext();
        $definition = static::createStub(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(25);
        $criteria->setIncludes([]);

        $result = new EntitySearchResult('product', 0, new EntityCollection(), null, $criteria, $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $capturedPayload = null;
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload, $criteria): Criteria {
                $capturedPayload = $payload;

                return $criteria;
            });

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
        ($tool)('product');

        static::assertIsArray($capturedPayload);
        static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $capturedPayload['total-count-mode']);
    }

    public function testCallerCanOverrideTotalCountMode(): void
    {
        $context = Context::createDefaultContext();
        $definition = static::createStub(EntityDefinition::class);

        $criteria = new Criteria();
        $criteria->setLimit(25);
        $criteria->setIncludes([]);

        $result = new EntitySearchResult('product', 0, new EntityCollection(), null, $criteria, $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $capturedPayload = null;
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload, $criteria): Criteria {
                $capturedPayload = $payload;

                return $criteria;
            });

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
        ($tool)('product', '{"total-count-mode": 0}');

        static::assertIsArray($capturedPayload);
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $capturedPayload['total-count-mode']);
    }

    public function testMalformedCriteriaJsonReturnsError(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntitySearchTool(
            $registry,
            static::createStub(RequestCriteriaBuilder::class),
            $contextProvider,
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );
        $output = ($tool)('product', 'not-json');

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

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, static::createStub(RequestCriteriaBuilder::class), $contextProvider, static::createStub(JsonEntityEncoder::class), static::createStub(AclCriteriaValidator::class));
        $output = ($tool)('product');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('product:read', $data['error']);
    }

    public function testDeniesAccessWhenCriteriaRequiresMissingAssociationPrivilege(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['order:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));
        $registry->method('getRepository')->willReturn($repository);

        $criteria = new Criteria();
        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($criteria);

        $criteriaValidator = $this->createMock(AclCriteriaValidator::class);
        $criteriaValidator->expects($this->once())
            ->method('validate')
            ->with('order', static::identicalTo($criteria), $context)
            ->willReturn(['order_customer:read']);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, static::createStub(JsonEntityEncoder::class), $criteriaValidator);
        $output = ($tool)('order', '{"associations": {"orderCustomer": {}}}');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege:', $data['error']);
        static::assertStringContainsString('order_customer:read', $data['error']);
    }

    public function testUnknownEntityReturnsError(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntitySearchTool(
            $registry,
            static::createStub(RequestCriteriaBuilder::class),
            $contextProvider,
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );
        $output = ($tool)('unknown_entity');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('unknown_entity', $data['error']);
        static::assertStringContainsString('shopware://entities', $data['error']);
    }
}
