<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityForeignKeyResolver::class)]
class EntityForeignKeyResolverTest extends TestCase
{
    private Connection&Stub $connection;

    private EntityForeignKeyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::createStub(Connection::class);

        $this->resolver = new EntityForeignKeyResolver(
            $this->connection,
            static::createStub(EntityDefinitionQueryHelper::class)
        );
    }

    public function testMetaFieldsEnrichedInRestrictions(): void
    {
        $mediaId = Uuid::randomHex();
        $cmsPageId = Uuid::randomHex();
        $downloadId = Uuid::randomHex();

        $emptyResult = static::createStub(Result::class);

        $downloadResult = static::createStub(Result::class);
        $downloadResult->method('fetchAllAssociative')->willReturn([
            [
                'id' => $downloadId,
                '_id' => Uuid::fromHexToBytes($mediaId),
                '_fileName' => 'shopware-logo',
                '_fileExtension' => 'png',
            ],
        ]);

        $cmsPageResult = static::createStub(Result::class);
        $cmsPageResult->method('fetchAllAssociative')->willReturn([
            [
                'id' => $cmsPageId,
                '_id' => Uuid::fromHexToBytes($mediaId),
                '_fileName' => 'shopware-logo',
                '_fileExtension' => 'png',
            ],
        ]);

        $this->connection->method('executeQuery')->willReturnOnConsecutiveCalls(
            $downloadResult,
            $emptyResult,
            $emptyResult,
            $emptyResult,
            $cmsPageResult,
            $emptyResult,
            $emptyResult,
            $emptyResult,
        );

        $rootDef = $this->buildRegistry();

        $result = $this->resolver->getAffectedDeleteRestrictions(
            $rootDef,
            [['id' => $mediaId]],
            Context::createDefaultContext(),
            true
        );

        static::assertSame([
            'product_download' => [
                [
                    'id' => $downloadId,
                    'media' => [
                        'id' => $mediaId,
                        'fileExtension' => 'png',
                        'fileName' => 'shopware-logo',
                    ],
                ],
            ],
            'cms_page' => [
                [
                    'id' => $cmsPageId,
                    'media' => [
                        'id' => $mediaId,
                        'fileExtension' => 'png',
                        'fileName' => 'shopware-logo',
                    ],
                ],
            ],
        ], $result);
    }

    #[TestDox('Restrict-delete meta field aliases are backtick-escaped so the query stays valid on MariaDB')]
    public function testRestrictDeleteMetaFieldAliasesAreEscaped(): void
    {
        $capturedQueries = [];

        $emptyResult = static::createStub(Result::class);
        $emptyResult->method('fetchAllAssociative')->willReturn([]);

        // getSQL() needs a platform to render the query; use MariaDB, the engine that rejects the alias.
        $this->connection->method('getDatabasePlatform')->willReturn(new MariaDBPlatform());

        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $query) use (&$capturedQueries, $emptyResult): Result {
                $capturedQueries[] = $query;

                return $emptyResult;
            }
        );

        $this->resolver->getAffectedDeleteRestrictions(
            $this->buildRegistry(),
            [['id' => Uuid::randomHex()]],
            Context::createDefaultContext(),
            true
        );

        $queries = implode("\n", $capturedQueries);

        static::assertNotCount(0, $capturedQueries);

        // The meta field aliases must be backtick-escaped. An unescaped leading-underscore alias
        // (e.g. `as _fileName`) is parsed by MariaDB as a charset introducer and fails with
        // "ERROR 1064 ... near '_fileName'" (regression from #17329).
        static::assertStringContainsString('as `_id`', $queries);
        static::assertStringContainsString('as `_fileName`', $queries);
        static::assertStringContainsString('as `_fileExtension`', $queries);
        static::assertStringNotContainsString('as _fileName', $queries);
        static::assertStringNotContainsString('as _fileExtension', $queries);
    }

    #[TestDox('A cascade delete that cycles back into an already resolved entity stops instead of recursing forever')]
    public function testCascadeCycleBetweenTwoEntitiesTerminates(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $queries = 0;

        $this->connection->method('getDatabasePlatform')->willReturn(new MariaDBPlatform());
        $this->connection->method('executeQuery')->willReturnCallback(
            function (string $query) use ($parentId, $childId, &$queries): Result {
                if (++$queries > 10) {
                    static::fail('The cascade resolution did not terminate, it is walking the cycle endlessly.');
                }

                $result = static::createStub(Result::class);
                $result->method('fetchAllAssociative')->willReturn(
                    str_contains($query, '`cascade_parent.children`')
                        ? [['id' => $childId]]
                        : [['id' => $parentId]]
                );

                return $result;
            }
        );

        $parentDefinition = new CascadeCycleParentDefinition();

        new StaticDefinitionInstanceRegistry(
            [$parentDefinition, new CascadeCycleChildDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $affected = $this->resolver->getAffectedDeletes(
            $parentDefinition,
            [['id' => $parentId]],
            Context::createDefaultContext()
        );

        // the child cascades away with its parent, the parent must not be cascaded away by its own child
        static::assertSame(['cascade_child' => [$childId]], $affected);
    }

    #[TestDox('An entity with a composite primary key, like a translation, restricts the delete with all its key properties')]
    public function testCompositePrimaryKeyRestrictsDelete(): void
    {
        $rootId = Uuid::randomHex();
        $scopeId = Uuid::randomHex();

        $this->returnRowsFor('`composite_restrict_root.children`', [
            ['_id' => Uuid::fromHexToBytes($rootId), 'rootId' => $rootId, 'scopeId' => $scopeId],
        ]);

        $restrictions = $this->resolver->getAffectedDeleteRestrictions(
            $this->buildCompositeKeyRegistry(),
            [['id' => $rootId]],
            Context::createDefaultContext(),
            true
        );

        static::assertSame([
            'composite_restrict_child' => [
                ['rootId' => $rootId, 'scopeId' => $scopeId, 'composite_restrict_root' => ['id' => $rootId]],
            ],
        ], $restrictions);
    }

    #[TestDox('A many-to-many association restricts the delete with the key properties of its mapping entity')]
    public function testManyToManyAssociationRestrictsDelete(): void
    {
        $rootId = Uuid::randomHex();
        $targetId = Uuid::randomHex();

        $this->returnRowsFor('`composite_restrict_root.targets.mapping`', [
            ['_id' => Uuid::fromHexToBytes($rootId), 'rootId' => $rootId, 'targetId' => $targetId],
        ]);

        $restrictions = $this->resolver->getAffectedDeleteRestrictions(
            $this->buildCompositeKeyRegistry(),
            [['id' => $rootId]],
            Context::createDefaultContext(),
            true
        );

        static::assertSame([
            'composite_restrict_root_target' => [
                ['rootId' => $rootId, 'targetId' => $targetId, 'composite_restrict_root' => ['id' => $rootId]],
            ],
        ], $restrictions);
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function returnRowsFor(string $associationAlias, array $rows): void
    {
        $this->connection->method('getDatabasePlatform')->willReturn(new MariaDBPlatform());
        $this->connection->method('executeQuery')->willReturnCallback(
            static function (string $query) use ($associationAlias, $rows): Result {
                $result = static::createStub(Result::class);
                $result->method('fetchAllAssociative')->willReturn(str_contains($query, $associationAlias) ? $rows : []);

                return $result;
            }
        );
    }

    private function buildCompositeKeyRegistry(): CompositeRestrictRootDefinition
    {
        $rootDefinition = new CompositeRestrictRootDefinition();

        new StaticDefinitionInstanceRegistry(
            [
                $rootDefinition,
                new CompositeRestrictChildDefinition(),
                new CompositeRestrictTargetDefinition(),
                new CompositeRestrictRootTargetDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        return $rootDefinition;
    }

    private function buildRegistry(): MediaDefinition
    {
        $rootDef = new MediaDefinition();

        new StaticDefinitionInstanceRegistry(
            [
                $rootDef,
                new CmsPageDefinition(),
                new CmsBlockDefinition(),
                new CmsSectionDefinition(),
                new ProductDownloadDefinition(),
                new OrderLineItemDownloadDefinition(),
                new DocumentDefinition(),
                new DocumentFileDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        return $rootDef;
    }
}

/**
 * @internal
 */
class CascadeCycleParentDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'cascade_parent';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new OneToManyAssociationField('children', CascadeCycleChildDefinition::class, 'parent_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

/**
 * @internal
 */
class CascadeCycleChildDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'cascade_child';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('parent_id', 'parentId', CascadeCycleParentDefinition::class),
            (new ManyToOneAssociationField('parent', 'parent_id', CascadeCycleParentDefinition::class, 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

/**
 * @internal
 */
class CompositeRestrictRootDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'composite_restrict_root';
    }

    public function getRestrictDeleteMetaFields(): FieldCollection
    {
        return $this->getFields()->filter(static fn (Field $field): bool => $field->getPropertyName() === 'id');
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new OneToManyAssociationField('children', CompositeRestrictChildDefinition::class, 'root_id'))->addFlags(new RestrictDelete()),
            (new ManyToManyAssociationField('targets', CompositeRestrictTargetDefinition::class, CompositeRestrictRootTargetDefinition::class, 'root_id', 'target_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}

/**
 * @internal
 */
class CompositeRestrictChildDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'composite_restrict_child';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('root_id', 'rootId', CompositeRestrictRootDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('scope_id', 'scopeId', CompositeRestrictTargetDefinition::class))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}

/**
 * @internal
 */
class CompositeRestrictTargetDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'composite_restrict_target';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}

/**
 * @internal
 */
class CompositeRestrictRootTargetDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'composite_restrict_root_target';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('root_id', 'rootId', CompositeRestrictRootDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('target_id', 'targetId', CompositeRestrictTargetDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('root', 'root_id', CompositeRestrictRootDefinition::class, 'id', false),
            new ManyToOneAssociationField('target', 'target_id', CompositeRestrictTargetDefinition::class, 'id', false),
        ]);
    }
}
