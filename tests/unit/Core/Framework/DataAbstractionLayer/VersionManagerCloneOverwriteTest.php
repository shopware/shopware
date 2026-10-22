<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(VersionManager::class)]
#[Package('framework')]
class VersionManagerCloneOverwriteTest extends TestCase
{
    private VersionManager $manager;

    private StaticDefinitionInstanceRegistry $registry;

    private EntityWriterInterface&MockObject $writer;

    private SerializerInterface&MockObject $serializer;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ProtectedCloneOverwriteDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $reader = static::createStub(EntityReaderInterface::class);
        $reader->method('read')->willReturn(new EntityCollection([new ArrayEntity(['id' => Uuid::randomHex()])]));
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->writer = $this->createMock(EntityWriterInterface::class);
        $this->manager = new VersionManager(
            $this->writer,
            $reader,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $this->serializer,
            $this->registry,
            static::createStub(VersionCommitDefinition::class),
            static::createStub(VersionCommitDataDefinition::class),
            static::createStub(VersionDefinition::class),
            static::createStub(LockFactory::class)
        );
    }

    /**
     * @param array<string, mixed> $overwrites
     */
    #[DataProvider('protectedCloneOverwriteProvider')]
    public function testCloneRejectsProtectedOverwritesBeforeWriting(array $overwrites, string $path): void
    {
        $this->serializer->method('serialize')->willReturn('{}');
        $this->writer->expects($this->never())->method('insert');

        try {
            $this->manager->clone(
                $this->registry->getByEntityName('protected_overwrite_test'),
                Uuid::randomHex(),
                Uuid::randomHex(),
                Uuid::randomHex(),
                WriteContext::createFromContext(Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()))),
                new CloneBehavior($overwrites)
            );
            static::fail('Protected overwrites must be rejected before insertion.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', $exception->getErrorCode());
            static::assertSame($path, $exception->getPath());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function protectedCloneOverwriteProvider(): iterable
    {
        yield 'root field' => [['protected' => 'blocked'], '/protected'];
        yield 'null protected field' => [['protected' => null], '/protected'];
        yield 'many to one' => [['parent' => ['protected' => 'blocked']], '/parent/protected'];
        yield 'one to one' => [['detail' => ['protected' => 'blocked']], '/detail/protected'];
        yield 'one to many' => [['items' => [['protected' => 'blocked']]], '/items/0/protected'];
        yield 'many to many target' => [['related' => [['protected' => 'blocked']]], '/related/0/protected'];
        yield 'language keyed translations' => [['translations' => ['en-GB' => ['protected' => 'blocked']]], '/translations/en-GB/protected'];
        yield 'deep association' => [['items' => [['parent' => ['protected' => 'blocked']]]], '/items/0/parent/protected'];
        yield 'extension field' => [['extensions' => ['protected' => 'blocked']], '/extensions/protected'];
        yield 'extension association' => [['extensions' => ['extra' => ['protected' => 'blocked']]], '/extensions/extra/protected'];
        yield 'nested extensions' => [['parent' => ['extensions' => ['protected' => 'blocked']]], '/parent/extensions/protected'];
    }

    public function testClonePreservesCopiedProtectedValuesAndAllowsNestedOverwrites(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        $this->serializer->method('serialize')->willReturn('{"protected":"copied"}');
        $this->writer->expects($this->once())->method('insert')->with(
            $this->registry->getByEntityName('protected_overwrite_test'),
            static::callback(static function (array $data): bool {
                static::assertSame('copied', $data[0]['protected']);
                static::assertSame(['name' => 'allowed'], $data[0]['parent']);
                static::assertSame(['protected' => 'ordinary JSON data'], $data[0]['json']);

                return true;
            }),
            static::anything()
        )->willReturn(['protected_overwrite_test' => [new EntityWriteResult('1', [], 'protected_overwrite_test', EntityWriteResult::OPERATION_INSERT)]]);

        $this->manager->clone(
            $this->registry->getByEntityName('protected_overwrite_test'),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Defaults::LIVE_VERSION,
            WriteContext::createFromContext($context),
            new CloneBehavior(['parent' => ['name' => 'allowed'], 'json' => ['protected' => 'ordinary JSON data']])
        );
    }

    public function testCloneAllowsProtectedNestedOverwritesInSystemScope(): void
    {
        $context = Context::createDefaultContext();
        $this->serializer->method('serialize')->willReturn('{}');
        $this->writer->expects($this->once())->method('insert')->with(
            $this->registry->getByEntityName('protected_overwrite_test'),
            static::callback(static fn (array $data): bool => $data[0]['parent'] === ['protected' => 'allowed']),
            static::anything()
        )->willReturn(['protected_overwrite_test' => [new EntityWriteResult('1', [], 'protected_overwrite_test', EntityWriteResult::OPERATION_INSERT)]]);

        $this->manager->clone(
            $this->registry->getByEntityName('protected_overwrite_test'),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Defaults::LIVE_VERSION,
            WriteContext::createFromContext($context),
            new CloneBehavior(['parent' => ['protected' => 'allowed']])
        );
    }
}

/**
 * @internal
 */
class ProtectedCloneOverwriteDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'protected_overwrite_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new StringField('name', 'name'),
            (new StringField('protected', 'protected'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new JsonField('json', 'json'),
            new ManyToOneAssociationField('parent', 'parent_id', self::class),
            new OneToOneAssociationField('detail', 'detail_id', 'id', self::class, false),
            new OneToManyAssociationField('items', self::class, 'parent_id'),
            new ManyToManyAssociationField('related', self::class, self::class, 'source_id', 'target_id'),
            (new TranslationsAssociationField(self::class, 'parent_id'))->removeFlag(CascadeDelete::class),
            (new ManyToOneAssociationField('extra', 'extra_id', self::class))->addFlags(new Extension()),
        ]);
    }
}
