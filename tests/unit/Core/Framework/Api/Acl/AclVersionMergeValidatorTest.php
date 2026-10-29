<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclVersionMergeValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AclVersionMergeValidator::class)]
class AclVersionMergeValidatorTest extends TestCase
{
    private ContainerBuilder $container;

    private StaticDefinitionInstanceRegistry $registry;

    private AclVersionMergeValidator $validator;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        $this->registry = new StaticDefinitionInstanceRegistry(
            [
                VersionedEntityDefinition::class,
                VersionedChildDefinition::class,
                VersionedGrandchildDefinition::class,
                OtherEntityDefinition::class,
                OtherEntityTranslationDefinition::class,
                VersionDefinition::class,
                VersionCommitDefinition::class,
                VersionCommitDataDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
            $this->container
        );

        $this->validator = new AclVersionMergeValidator($this->registry);
    }

    public function testRequiresUpdatePrivilegeForTheMergedEntity(): void
    {
        $this->recordChanges([]);

        $missing = $this->validator->getMissingPrivileges(
            Uuid::randomHex(),
            $this->registry->getByEntityName('versioned_entity'),
            $this->createAdminApiContext(['versioned_entity:read'])
        );

        static::assertSame(['versioned_entity:update'], $missing);
    }

    public function testRequiresThePrivilegeOfEachChangeOfOtherEntities(): void
    {
        $this->recordChanges(['other_entity' => ['insert', 'update', 'delete']]);

        $missing = $this->validator->getMissingPrivileges(
            Uuid::randomHex(),
            $this->registry->getByEntityName('versioned_entity'),
            $this->createAdminApiContext(['versioned_entity:update', 'other_entity:update'])
        );

        static::assertSame(['other_entity:create', 'other_entity:delete'], $missing);
    }

    public function testCoversChangesOfTheMergedEntityAndItsChildEntitiesWithItsUpdatePrivilege(): void
    {
        $this->recordChanges([
            'versioned_entity' => ['update'],
            'versioned_child' => ['insert', 'delete'],
            'versioned_grandchild' => ['insert', 'update', 'delete'],
        ]);

        $missing = $this->validator->getMissingPrivileges(
            Uuid::randomHex(),
            $this->registry->getByEntityName('versioned_entity'),
            $this->createAdminApiContext(['versioned_entity:update'])
        );

        static::assertSame([], $missing);
    }

    public function testChecksTranslationsOfOtherEntitiesWithThePrivilegeOfTheTranslatedEntity(): void
    {
        $this->recordChanges(['other_entity_translation' => ['update', 'insert']]);

        $missing = $this->validator->getMissingPrivileges(
            Uuid::randomHex(),
            $this->registry->getByEntityName('versioned_entity'),
            $this->createAdminApiContext(['versioned_entity:update', 'other_entity:update'])
        );

        static::assertSame(['other_entity:create'], $missing);
    }

    public function testIgnoresUnknownActionsAndUnknownEntities(): void
    {
        $this->recordChanges([
            'other_entity' => ['clone'],
            'unknown_entity' => ['update'],
        ]);

        $missing = $this->validator->getMissingPrivileges(
            Uuid::randomHex(),
            $this->registry->getByEntityName('versioned_entity'),
            $this->createAdminApiContext(['versioned_entity:update'])
        );

        static::assertSame([], $missing);
    }

    public function testReportsEachMissingPrivilegeOnce(): void
    {
        $this->recordChanges([
            'other_entity' => ['update'],
            'other_entity_translation' => ['update'],
        ]);

        $missing = $this->validator->getMissingPrivileges(
            Uuid::randomHex(),
            $this->registry->getByEntityName('versioned_entity'),
            $this->createAdminApiContext([])
        );

        static::assertSame(['versioned_entity:update', 'other_entity:update'], $missing);
    }

    public function testReadsTheChangesOfTheMergedVersion(): void
    {
        $versionId = Uuid::randomHex();

        $commitDataRepository = $this->createMock(EntityRepository::class);
        $commitDataRepository->expects($this->once())->method('aggregate')
            ->willReturnCallback(static function (Criteria $criteria) use ($versionId): AggregationResultCollection {
                static::assertEquals([new EqualsFilter('commit.versionId', $versionId)], $criteria->getFilters());

                return new AggregationResultCollection([new TermsResult('entities', [])]);
            });
        $this->container->set(VersionCommitDataDefinition::ENTITY_NAME . '.repository', $commitDataRepository);

        $this->validator->getMissingPrivileges(
            $versionId,
            $this->registry->getByEntityName('versioned_entity'),
            $this->createAdminApiContext(['versioned_entity:update'])
        );
    }

    #[DataProvider('versionWithoutRecordedChangesProvider')]
    public function testOnlyRequiresUpdatePrivilegeWhenNoChangesAreRecorded(string $entity, string $versionId): void
    {
        $commitDataRepository = $this->createMock(EntityRepository::class);
        $commitDataRepository->expects($this->never())->method('aggregate');
        $this->container->set(VersionCommitDataDefinition::ENTITY_NAME . '.repository', $commitDataRepository);

        $missing = $this->validator->getMissingPrivileges(
            $versionId,
            $this->registry->getByEntityName($entity),
            $this->createAdminApiContext([])
        );

        static::assertSame([$entity . ':update'], $missing);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function versionWithoutRecordedChangesProvider(): iterable
    {
        yield 'the live version has no change set' => ['versioned_entity', Defaults::LIVE_VERSION];
        yield 'an entity without version support has no change set' => ['other_entity', Uuid::randomHex()];
    }

    /**
     * @param array<string, list<string>> $changes actions the version recorded, by entity name
     */
    private function recordChanges(array $changes): void
    {
        $buckets = [];
        foreach ($changes as $entityName => $actions) {
            $actionBuckets = array_map(static fn (string $action): Bucket => new Bucket($action, 1, null), $actions);
            $buckets[] = new Bucket($entityName, \count($actions), new TermsResult('actions', $actionBuckets));
        }

        $commitDataRepository = static::createStub(EntityRepository::class);
        $commitDataRepository->method('aggregate')->willReturn(new AggregationResultCollection([new TermsResult('entities', $buckets)]));

        $this->container->set(VersionCommitDataDefinition::ENTITY_NAME . '.repository', $commitDataRepository);
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
class OtherEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'other_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}

/**
 * @internal
 */
class OtherEntityTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'other_entity_translation';
    }

    protected function getParentDefinitionClass(): string
    {
        return OtherEntityDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
