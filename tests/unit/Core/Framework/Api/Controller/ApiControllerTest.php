<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController\ChildDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController\ParentDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiController::class)]
class ApiControllerTest extends TestCase
{
    public function testListWithOneToOneAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondChildOneToOneParent.id', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            static::createStub(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-child-one-to-one', $parentId)
        );
    }

    public function testListWithManyToOneAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondManyToOneParents.id', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            static::createStub(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-child-many-to-one', $parentId)
        );
    }

    public function testListWithOneToManyAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondParentOneToManyId', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            static::createStub(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-one-to-many-children', $parentId)
        );
    }

    public function testDeleteVersionSuppressesTheAuditLogAndDropsTheChangeSet(): void
    {
        $entityId = Uuid::randomHex();
        $versionId = Uuid::randomHex();
        $commitId = Uuid::randomHex();

        $calls = [];

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->once())->method('delete')
            ->willReturnCallback(function (array $ids, Context $context) use (&$calls, $entityId, $versionId): EntityWrittenContainerEvent {
                $calls[] = 'delete-entity';

                static::assertSame([['id' => $entityId]], $ids);
                static::assertSame($versionId, $context->getVersionId());
                static::assertTrue($context->hasState(VersionManager::DISABLE_AUDIT_LOG));

                return static::createStub(EntityWrittenContainerEvent::class);
            });

        $commitRepository = $this->createMock(EntityRepository::class);
        $commitRepository->expects($this->once())->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use (&$calls, $versionId, $commitId): IdSearchResult {
                $calls[] = 'search-commits';

                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('versionId', $filter->getField());
                static::assertSame($versionId, $filter->getValue());

                return IdSearchResult::fromIds([$commitId], $criteria, $context);
            });
        $commitRepository->expects($this->once())->method('delete')
            ->willReturnCallback(function (array $ids) use (&$calls, $commitId): EntityWrittenContainerEvent {
                $calls[] = 'delete-commits';

                static::assertSame([['id' => $commitId]], $ids);

                return static::createStub(EntityWrittenContainerEvent::class);
            });

        $versionRepository = $this->createMock(EntityRepository::class);
        $versionRepository->expects($this->once())->method('delete')
            ->willReturnCallback(function (array $ids) use (&$calls, $versionId): EntityWrittenContainerEvent {
                $calls[] = 'delete-version';

                static::assertSame([['id' => $versionId]], $ids);

                return static::createStub(EntityWrittenContainerEvent::class);
            });

        $container = new ContainerBuilder();
        $container->set('parent_entity.repository', $entityRepository);
        $container->set(VersionCommitDefinition::ENTITY_NAME . '.repository', $commitRepository);
        $container->set(VersionDefinition::ENTITY_NAME . '.repository', $versionRepository);

        $registry = new StaticDefinitionInstanceRegistry(
            [
                ParentDefinition::class,
                ChildDefinition::class,
                VersionDefinition::class,
                VersionCommitDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
            $container
        );

        $controller = new ApiController(
            $registry,
            static::createStub(DecoderInterface::class),
            static::createStub(RequestCriteriaBuilder::class),
            static::createStub(EntityProtectionValidator::class),
            static::createStub(AclCriteriaValidator::class)
        );

        $controller->deleteVersion(Context::createDefaultContext(), 'parent-entity', $entityId, $versionId);

        static::assertSame([
            'delete-entity',
            'search-commits',
            'delete-commits',
            'delete-version',
        ], $calls, 'the change set must be dropped before the version row');
    }

    public function testDeleteVersionWithoutChangeSetLeavesTheCommitRepositoryAlone(): void
    {
        $entityId = Uuid::randomHex();
        $versionId = Uuid::randomHex();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->once())->method('delete')
            ->willReturn(static::createStub(EntityWrittenContainerEvent::class));

        $commitRepository = $this->createMock(EntityRepository::class);
        $commitRepository->expects($this->once())->method('searchIds')
            ->willReturnCallback(static fn (Criteria $criteria, Context $context): IdSearchResult => IdSearchResult::fromIds([], $criteria, $context));
        $commitRepository->expects($this->never())->method('delete');

        $versionRepository = $this->createMock(EntityRepository::class);
        $versionRepository->expects($this->once())->method('delete')
            ->willReturnCallback(function (array $ids) use ($versionId): EntityWrittenContainerEvent {
                static::assertSame([['id' => $versionId]], $ids);

                return static::createStub(EntityWrittenContainerEvent::class);
            });

        $container = new ContainerBuilder();
        $container->set('parent_entity.repository', $entityRepository);
        $container->set(VersionCommitDefinition::ENTITY_NAME . '.repository', $commitRepository);
        $container->set(VersionDefinition::ENTITY_NAME . '.repository', $versionRepository);

        $controller = $this->createControllerWithRegistry($container, [ParentDefinition::class, ChildDefinition::class, VersionDefinition::class, VersionCommitDefinition::class]);

        $controller->deleteVersion(Context::createDefaultContext(), 'parent-entity', $entityId, $versionId);
    }

    public function testCreateVersionRequiresReadPrivilege(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->never())->method('createVersion');

        $container = new ContainerBuilder();
        $container->set('parent_entity.repository', $entityRepository);

        $controller = $this->createControllerWithRegistry($container, [ParentDefinition::class, ChildDefinition::class]);

        $this->expectExceptionObject(ApiException::missingPrivileges(['parent_entity:read']));

        $controller->createVersion(new Request(), $this->createAdminApiContext([]), 'parent-entity', Uuid::randomHex());
    }

    public function testDeleteVersionRequiresDeletePrivilegeBeforeAnythingIsRemoved(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->never())->method('delete');

        $commitRepository = $this->createMock(EntityRepository::class);
        $commitRepository->expects($this->never())->method('delete');

        $versionRepository = $this->createMock(EntityRepository::class);
        $versionRepository->expects($this->never())->method('delete');

        $container = new ContainerBuilder();
        $container->set('parent_entity.repository', $entityRepository);
        $container->set(VersionCommitDefinition::ENTITY_NAME . '.repository', $commitRepository);
        $container->set(VersionDefinition::ENTITY_NAME . '.repository', $versionRepository);

        $controller = $this->createControllerWithRegistry($container, [ParentDefinition::class, ChildDefinition::class, VersionDefinition::class, VersionCommitDefinition::class]);

        $this->expectExceptionObject(ApiException::missingPrivileges(['parent_entity:delete']));

        $controller->deleteVersion($this->createAdminApiContext(['parent_entity:read', 'version:delete']), 'parent-entity', Uuid::randomHex(), Uuid::randomHex());
    }

    public function testMergeVersionRequiresUpdatePrivilegeForTheEntity(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->never())->method('merge');

        $controller = $this->createMergeController($entityRepository, []);

        $this->expectExceptionObject(ApiException::missingPrivileges(['versioned_entity:update']));

        $controller->mergeVersion($this->createAdminApiContext(['versioned_entity:read']), 'versioned-entity', Uuid::randomHex());
    }

    public function testMergeVersionRequiresPrivilegesForAllRecordedChanges(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->never())->method('merge');

        $controller = $this->createMergeController($entityRepository, [
            'versioned_entity' => ['clone', 'update'],
            'versioned_child' => ['insert', 'delete'],
            'versioned_grandchild' => ['insert', 'update'],
            'parent_entity' => ['insert', 'update', 'delete'],
            'child_entity' => ['clone'],
            'unknown_entity' => ['update'],
        ]);

        $this->expectExceptionObject(ApiException::missingPrivileges(['parent_entity:create', 'parent_entity:delete']));

        $controller->mergeVersion($this->createAdminApiContext(['versioned_entity:update', 'parent_entity:update']), 'versioned-entity', Uuid::randomHex());
    }

    public function testMergeVersionChecksTranslationsOfOtherEntitiesWithTheParentPrivilege(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->never())->method('merge');

        $controller = $this->createMergeController($entityRepository, ['parent_entity_translation' => ['update', 'insert']]);

        $this->expectExceptionObject(ApiException::missingPrivileges(['parent_entity:create']));

        $controller->mergeVersion($this->createAdminApiContext(['versioned_entity:update', 'parent_entity:update']), 'versioned-entity', Uuid::randomHex());
    }

    public function testMergeVersionReportsAllMissingPrivilegesAtOnce(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->never())->method('merge');

        $controller = $this->createMergeController($entityRepository, ['parent_entity' => ['update']]);

        $this->expectExceptionObject(ApiException::missingPrivileges(['versioned_entity:update', 'parent_entity:update']));

        $controller->mergeVersion($this->createAdminApiContext(['versioned_entity:read']), 'versioned-entity', Uuid::randomHex());
    }

    /**
     * @param array<string, list<string>> $recordedChanges
     */
    #[DataProvider('mergeableVersionProvider')]
    public function testMergeVersionWithAllPrivilegesMergesInSystemScope(string $entity, string $versionId, array $recordedChanges): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->once())->method('merge')
            ->willReturnCallback(static function (string $mergedVersionId, Context $context) use ($versionId): void {
                static::assertSame($versionId, $mergedVersionId);
                static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
            });

        $controller = $this->createMergeController($entityRepository, $recordedChanges);

        $response = $controller->mergeVersion($this->createAdminApiContext(['versioned_entity:update', 'parent_entity:update']), $entity, $versionId);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, string, array<string, list<string>>}>
     */
    public static function mergeableVersionProvider(): iterable
    {
        yield 'the user may write all recorded changes' => ['versioned-entity', Uuid::randomHex(), ['versioned_entity' => ['clone', 'update'], 'parent_entity' => ['update'], 'child_entity' => ['clone']]];
        yield 'changes of child entities are covered by the merged entity' => ['versioned-entity', Uuid::randomHex(), ['versioned_child' => ['insert', 'delete'], 'versioned_grandchild' => ['update']]];
        yield 'a version without changes is left to the merge, which rejects it' => ['versioned-entity', Uuid::randomHex(), []];
        yield 'the live version is left to the merge, which rejects it' => ['versioned-entity', Defaults::LIVE_VERSION, ['child_entity' => ['update']]];
        yield 'an entity without versions is left to the merge, which rejects it' => ['parent-entity', Uuid::randomHex(), ['child_entity' => ['update']]];
    }

    public function testCreateWithAnIdInThePathIsNotAllowed(): void
    {
        $entityId = Uuid::randomHex();
        $path = '/api/parent-entity/' . $entityId;

        $request = new Request(
            [],
            ['name' => 'created'],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $path],
        );

        $container = new ContainerBuilder();
        $container->set('parent_entity.repository', static::createStub(EntityRepository::class));

        $controller = $this->createControllerWithRegistry($container, [ParentDefinition::class, ChildDefinition::class]);

        $this->expectExceptionObject(ApiException::methodNotAllowed(
            ['GET', 'PATCH', 'DELETE'],
            'No route found for "POST ' . $path . '": Method Not Allowed (Allow: GET, PATCH, DELETE)',
        ));

        $controller->create($request, Context::createDefaultContext(), static::createStub(ResponseFactoryInterface::class), 'parent-entity', '/' . $entityId);
    }

    /**
     * @param list<string> $permissions
     */
    private function createAdminApiContext(array $permissions): Context
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions($permissions);

        return Context::createDefaultContext($source);
    }

    /**
     * @param EntityRepository<EntityCollection<Entity>> $entityRepository
     * @param array<string, list<string>> $recordedChanges actions the version recorded, by entity name
     */
    private function createMergeController(EntityRepository $entityRepository, array $recordedChanges): ApiController
    {
        $buckets = [];
        foreach ($recordedChanges as $entityName => $actions) {
            $actionBuckets = array_map(static fn (string $action): Bucket => new Bucket($action, 1, null), $actions);
            $buckets[] = new Bucket($entityName, \count($actions), new TermsResult('actions', $actionBuckets));
        }

        $commitDataRepository = static::createStub(EntityRepository::class);
        $commitDataRepository->method('aggregate')->willReturn(new AggregationResultCollection([new TermsResult('entities', $buckets)]));

        $container = new ContainerBuilder();
        $container->set('versioned_entity.repository', $entityRepository);
        $container->set('parent_entity.repository', $entityRepository);
        $container->set(VersionCommitDataDefinition::ENTITY_NAME . '.repository', $commitDataRepository);

        return $this->createControllerWithRegistry($container, [
            VersionedEntityDefinition::class,
            VersionedChildDefinition::class,
            VersionedGrandchildDefinition::class,
            ParentDefinition::class,
            ParentTranslationDefinition::class,
            ChildDefinition::class,
            VersionDefinition::class,
            VersionCommitDefinition::class,
            VersionCommitDataDefinition::class,
        ]);
    }

    /**
     * @param list<class-string<EntityDefinition>> $definitions
     */
    private function createControllerWithRegistry(ContainerBuilder $container, array $definitions): ApiController
    {
        $registry = new StaticDefinitionInstanceRegistry(
            $definitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
            $container
        );

        return new ApiController(
            $registry,
            static::createStub(DecoderInterface::class),
            static::createStub(RequestCriteriaBuilder::class),
            static::createStub(EntityProtectionValidator::class),
            static::createStub(AclCriteriaValidator::class)
        );
    }

    private function createApiController(
        string $expectedFilterField,
        string $parentId,
    ): ApiController {
        $container = $this->createContainer($expectedFilterField, $parentId);

        $definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [ParentDefinition::class, ChildDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
            $container
        );

        $aggregationParser = new AggregationParser();

        $requestCriteriaBuilder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($definitionInstanceRegistry),
            new CriteriaArrayConverter($aggregationParser),
            static::createStub(CompressedCriteriaDecoder::class)
        );

        return new ApiController(
            $definitionInstanceRegistry,
            static::createStub(DecoderInterface::class),
            $requestCriteriaBuilder,
            static::createStub(EntityProtectionValidator::class),
            static::createStub(AclCriteriaValidator::class)
        );
    }

    private function createContainer(string $expectedFilterField, string $parentId): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $parentDefinition = new Definition(StaticEntityRepository::class);
        $parentDefinition->setArguments([[], new ParentDefinition()]);

        $childDefinition = new Definition(StaticEntityRepository::class);
        $childDefinition->setArguments([[], new ChildDefinition()]);

        $container->setDefinitions([
            'parent_entity.repository' => $parentDefinition,
            'child_entity.repository' => $childDefinition,
        ]);

        $container->set('parent_entity.repository', static::createStub(EntityRepository::class));

        $childRepo = static::createStub(EntityRepository::class);
        $childRepo->method('search')->willReturnCallback(static function (Criteria $criteria, Context $context) use ($expectedFilterField, $parentId): EntitySearchResult {
            $filter = $criteria->getFilters()[0];
            static::assertInstanceOf(EqualsFilter::class, $filter);
            static::assertSame($expectedFilterField, $filter->getField());
            static::assertSame($parentId, $filter->getValue());

            return new EntitySearchResult(
                'child_entity',
                0,
                new EntityCollection(),
                null,
                $criteria,
                $context
            );
        });
        $container->set('child_entity.repository', $childRepo);

        return $container;
    }
}

/**
 * @internal
 */
class VersionedEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'versioned_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
        ]);
    }
}

/**
 * @internal
 */
class VersionedChildDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'versioned_child';
    }

    protected function getParentDefinitionClass(): string
    {
        return VersionedEntityDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
        ]);
    }
}

/**
 * @internal
 */
class VersionedGrandchildDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'versioned_grandchild';
    }

    protected function getParentDefinitionClass(): string
    {
        return VersionedChildDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
        ]);
    }
}

/**
 * @internal
 */
class ParentTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'parent_entity_translation';
    }

    protected function getParentDefinitionClass(): string
    {
        return ParentDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
