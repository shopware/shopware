<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityReadTool::class)]
class EntityReadToolTest extends TestCase
{
    public function testReturnsDataWhenEntityFound(): void
    {
        $context = Context::createDefaultContext();
        $entity = new ArrayEntity(['id' => 'prod-123', 'name' => 'Test Product']);
        $entity->internalSetEntityData('product', new FieldVisibility([]));
        $collection = new EntitySearchResult(
            'product',
            1,
            new EntityCollection([$entity]),
            null,
            new Criteria(['prod-123']),
            $context,
        );

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($collection);

        $definition = static::createStub(EntityDefinition::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $readCriteria = new Criteria(['prod-123']);
        $readCriteria->setIncludes([]);
        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($readCriteria);

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn(['id' => 'prod-123', 'name' => 'Test Product']);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
        $output = ($tool)('product', 'prod-123');

        static::assertFalse($output->isError());
        static::assertNotNull($output->data);
        static::assertNull($output->error);
        static::assertSame('prod-123', $output->data['id']);
        static::assertSame('Test Product', $output->data['name']);
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

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($emptyCollection);

        $definition = static::createStub(EntityDefinition::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getRepository')->willReturn($repository);

        $missingCriteria = new Criteria(['prod-missing']);
        $missingCriteria->setIncludes([]);
        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($missingCriteria);

        $encoder = static::createStub(JsonEntityEncoder::class);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, $encoder, static::createStub(AclCriteriaValidator::class));
        $output = ($tool)('product', 'prod-missing');

        static::assertTrue($output->isError());
        static::assertSame(McpToolError::NOT_FOUND, $output->error->code);
        static::assertNotNull($output->error);
        static::assertNull($output->data);
        static::assertSame('Entity "product" with ID "prod-missing" not found.', $output->error->message);
    }

    public function testMalformedCriteriaJsonReturnsError(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityReadTool(
            $registry,
            static::createStub(RequestCriteriaBuilder::class),
            $contextProvider,
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );
        $output = ($tool)('product', 'some-id', 'not-json');

        static::assertTrue($output->isError());
        static::assertStringContainsString('Invalid JSON', $output->error->message);
        static::assertStringContainsString('criteria', $output->error->message);
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

        $tool = new EntityReadTool($registry, static::createStub(RequestCriteriaBuilder::class), $contextProvider, static::createStub(JsonEntityEncoder::class), static::createStub(AclCriteriaValidator::class));
        $output = ($tool)('product', 'some-id');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('product:read', $output->error->message);
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

        $criteria = new Criteria(['order-id']);
        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($criteria);

        $criteriaValidator = $this->createMock(AclCriteriaValidator::class);
        $criteriaValidator->expects($this->once())
            ->method('validate')
            ->with('order', static::identicalTo($criteria), $context)
            ->willReturn(['order_customer:read']);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, static::createStub(JsonEntityEncoder::class), $criteriaValidator);
        $output = ($tool)('order', 'order-id', '{"associations": {"orderCustomer": {}}}');

        static::assertTrue($output->isError());
        static::assertStringContainsString('Missing privilege:', $output->error->message);
        static::assertStringContainsString('order_customer:read', $output->error->message);
    }

    public function testUnknownEntityReturnsError(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityReadTool(
            $registry,
            static::createStub(RequestCriteriaBuilder::class),
            $contextProvider,
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );
        $output = ($tool)('unknown_entity', 'some-id');

        static::assertTrue($output->isError());
        static::assertStringContainsString('unknown_entity', $output->error->message);
        static::assertStringContainsString('shopware://entities', $output->error->message);
        static::assertSame(McpToolError::INVALID_ARGUMENTS, $output->error->code);
    }

    #[TestDox('A malformed "includes" that makes the builder throw the base DataAbstractionLayerException is answered with its detail')]
    public function testAMalformedCriteriaIsAnsweredWithTheParserDetail(): void
    {
        $context = Context::createDefaultContext();

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));
        $registry->method('getRepository')->willReturn(static::createStub(EntityRepository::class));

        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willThrowException(
            DataAbstractionLayerException::expectedArrayWithType('includes', 'string')
        );

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityReadTool(
            $registry,
            $criteriaBuilder,
            $contextProvider,
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );

        $data = ($tool)('product', 'prod-123', '{"includes":"id"}');

        static::assertTrue($data->isError());
        static::assertStringContainsString('includes', $data->error->message);
    }

    public function testAnUnexpectedThrowableStillPropagates(): void
    {
        $context = Context::createDefaultContext();

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));
        $registry->method('getRepository')->willReturn(static::createStub(EntityRepository::class));

        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willThrowException(new \RuntimeException('bug, not bad input'));

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityReadTool(
            $registry,
            $criteriaBuilder,
            $contextProvider,
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );

        $this->expectExceptionObject(new \RuntimeException('bug, not bad input'));

        ($tool)('product', 'prod-123');
    }
}
