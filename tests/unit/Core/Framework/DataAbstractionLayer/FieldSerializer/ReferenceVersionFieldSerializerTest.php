<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[CoversClass(ReferenceVersionFieldSerializer::class)]
class ReferenceVersionFieldSerializerTest extends TestCase
{
    private ReferenceVersionFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionRegistry;

    private ReferenceVersionRootDefinition $rootDefinition;

    private ReferenceVersionChildDefinition $childDefinition;

    private ReferenceVersionPlainDefinition $plainDefinition;

    protected function setUp(): void
    {
        $this->serializer = new ReferenceVersionFieldSerializer();
        $this->definitionRegistry = new DefinitionInstanceRegistry(new Container(), [], []);

        $this->rootDefinition = new ReferenceVersionRootDefinition();
        $this->childDefinition = new ReferenceVersionChildDefinition();
        $this->plainDefinition = new ReferenceVersionPlainDefinition();

        $this->definitionRegistry->register($this->rootDefinition, ReferenceVersionRootDefinition::class);
        $this->definitionRegistry->register($this->childDefinition, ReferenceVersionChildDefinition::class);
        $this->definitionRegistry->register($this->plainDefinition, ReferenceVersionPlainDefinition::class);
        $this->definitionRegistry->register(new VersionDefinition(), VersionDefinition::class);
    }

    public function testEncodeUsesExplicitVersion(): void
    {
        $versionId = Uuid::randomHex();
        $field = $this->createField(ReferenceVersionRootDefinition::class);

        $encoded = iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair($field->getPropertyName(), $versionId, true),
            $this->createWriteParameterBag($this->plainDefinition)
        ));

        static::assertSame([$field->getStorageName() => Uuid::fromHexToBytes($versionId)], $encoded);
    }

    public function testEncodeUsesLiveVersionForSelfReferenceWithoutValue(): void
    {
        $field = $this->createField(ReferenceVersionRootDefinition::class);

        $encoded = iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair($field->getPropertyName(), null, true),
            $this->createWriteParameterBag($this->rootDefinition)
        ));

        static::assertSame([$field->getStorageName() => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], $encoded);
    }

    public function testEncodeUsesReferenceVersionFromWriteContext(): void
    {
        $versionId = Uuid::randomHex();
        $field = $this->createField(ReferenceVersionRootDefinition::class);

        $encoded = iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair($field->getPropertyName(), null, true),
            $this->createWriteParameterBag($this->plainDefinition, static function (WriteContext $writeContext) use ($versionId): void {
                $writeContext->set(ReferenceVersionRootDefinition::ENTITY_NAME, 'versionId', $versionId);
            })
        ));

        static::assertSame([$field->getStorageName() => Uuid::fromHexToBytes($versionId)], $encoded);
    }

    public function testEncodeUsesOwnVersionForChildDefinitionWithParentReference(): void
    {
        $versionId = Uuid::randomHex();
        $field = $this->createField(ReferenceVersionRootDefinition::class);

        $encoded = iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair($field->getPropertyName(), null, true),
            $this->createWriteParameterBag($this->childDefinition, static function (WriteContext $writeContext) use ($versionId): void {
                $writeContext->set(ReferenceVersionChildDefinition::ENTITY_NAME, 'versionId', $versionId);
            })
        ));

        static::assertSame([$field->getStorageName() => Uuid::fromHexToBytes($versionId)], $encoded);
    }

    public function testEncodeKeepsExistingValueForNonVersionAwareExistingEntityDefault(): void
    {
        $field = $this->createField(ReferenceVersionRootDefinition::class);

        $encoded = iterator_to_array($this->serializer->encode(
            $field,
            new EntityExistence(null, [], true, false, false, []),
            new KeyValuePair($field->getPropertyName(), null, true, true),
            $this->createWriteParameterBag($this->plainDefinition)
        ));

        static::assertSame([], $encoded);
    }

    public function testDecodeConvertsBytesToHex(): void
    {
        $versionId = Uuid::randomHex();

        static::assertSame(
            $versionId,
            $this->serializer->decode($this->createField(ReferenceVersionRootDefinition::class), Uuid::fromHexToBytes($versionId))
        );
    }

    /**
     * @param class-string<EntityDefinition> $referenceDefinition
     */
    private function createField(string $referenceDefinition): ReferenceVersionField
    {
        $field = new ReferenceVersionField($referenceDefinition);
        $field->compile($this->definitionRegistry);

        return $field;
    }

    /**
     * @param \Closure(WriteContext): void|null $configure
     */
    private function createWriteParameterBag(EntityDefinition $definition, ?\Closure $configure = null): WriteParameterBag
    {
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());
        $configure?->__invoke($writeContext);

        return new WriteParameterBag($definition, $writeContext, '', new WriteCommandQueue());
    }
}

/**
 * @internal
 */
class ReferenceVersionRootDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'reference_version_root';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
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
class ReferenceVersionChildDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'reference_version_child';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ReferenceVersionRootDefinition::class;
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
class ReferenceVersionPlainDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'reference_version_plain';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}
