<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\Api\Serializer\AssertValuesTrait;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\SerializationFixture;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicStruct;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithExtension;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToManyRelationships;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithSelfReference;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestInternalFieldsAreFiltered;
use Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestMainResourceShouldNotBeInIncluded;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonEntityEncoder::class)]
class JsonEntityEncoderTest extends TestCase
{
    use AssertValuesTrait;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class => ProductDefinition::class,
                MediaDefinition::class => MediaDefinition::class,
                UserDefinition::class => UserDefinition::class,
                MediaFolderDefinition::class => MediaFolderDefinition::class,
                RuleDefinition::class => RuleDefinition::class,
                CustomFieldTestDefinition::class => CustomFieldTestDefinition::class,
                CustomFieldTestTranslationDefinition::class => CustomFieldTestTranslationDefinition::class,
                ExtendableDefinition::class => ExtendableDefinition::class,
                ExtendedDefinition::class => ExtendedDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    /**
     * @return iterable<array<mixed>>
     */
    public static function emptyInputProvider(): iterable
    {
        yield 'empty input null' => [null];
        yield 'empty input string' => ['string'];
        yield 'empty input 1' => [1];
        yield 'empty input false' => [false];
        yield 'empty input date time' => [new \DateTime()];
        yield 'empty input 1 point 1' => [1.1];
    }

    #[DataProvider('emptyInputProvider')]
    public function testEncodeWithEmptyInput(mixed $input): void
    {
        $this->expectExceptionObject(ApiException::unsupportedEncoderInput());

        $encoder = $this->createEncoder();
        $encoder->encode(new Criteria(), $this->createDefinition(ProductDefinition::class), $input, SerializationFixture::API_BASE_URL);
    }

    /**
     * @return iterable<string, array{class-string<EntityDefinition>, SerializationFixture}>
     */
    public static function complexStructsProvider(): iterable
    {
        yield 'media resource with basic struct is encoded' => [MediaDefinition::class, new TestBasicStruct()];
        yield 'user resource with to many relationships is encoded' => [UserDefinition::class, new TestBasicWithToManyRelationships()];
        yield 'media resource with to one relationship is encoded' => [MediaDefinition::class, new TestBasicWithToOneRelationship()];
        yield 'media folder resource with self reference collection is encoded' => [MediaFolderDefinition::class, new TestCollectionWithSelfReference()];
        yield 'media resource with collection to one relationship is encoded' => [MediaDefinition::class, new TestCollectionWithToOneRelationship()];
        yield 'rule resource filters internal fields' => [RuleDefinition::class, new TestInternalFieldsAreFiltered()];
        yield 'user resource keeps main resource out of included data' => [UserDefinition::class, new TestMainResourceShouldNotBeInIncluded()];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('complexStructsProvider')]
    public function testEncodeComplexStructs(string $definitionClass, SerializationFixture $fixture): void
    {
        $definition = $this->createDefinition($definitionClass);
        $encoder = $this->createEncoder();
        $actual = $encoder->encode(new Criteria(), $definition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        $this->assertValues($fixture->getAdminJsonFixtures(), $actual);
    }

    public function testEncodeStructWithExtension(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions();
        $fixture = new TestBasicWithExtension();

        $encoder = $this->createEncoder();
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        unset($actual['apiAlias']);
        static::assertEquals($fixture->getAdminJsonFixtures(), $actual);
        $this->assertValues($fixture->getAdminJsonFixtures(), $actual);
    }

    public function testConcreteExtensionIncludeKeepsExtensionsWrapper(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions();
        $fixture = new TestBasicWithExtension();
        $input = $this->getExtendableInputWithEntityNames($fixture);

        $criteria = new Criteria();
        $criteria->setIncludes([
            'extendable' => ['id', 'toOne'],
        ]);

        $encoder = $this->createEncoder();
        $actual = $encoder->encode($criteria, $extendableDefinition, $input, SerializationFixture::API_BASE_URL);

        static::assertSame('1d23c1b015bf43fb97e89008cf42d6fe', $actual['id']);
        static::assertArrayNotHasKey('createdAt', $actual);
        static::assertArrayHasKey('extensions', $actual);
        static::assertArrayHasKey('toOne', $actual['extensions']);
        static::assertSame('toOne', $actual['extensions']['toOne']['name']);
    }

    public function testExtensionEntityIncludesFilterPlainJsonExtensionPayload(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions();
        $fixture = new TestBasicWithExtension();
        $input = $this->getExtendableInputWithEntityNames($fixture);

        $criteria = new Criteria();
        $criteria->setIncludes([
            'extendable' => ['id', 'extensions', 'toOne'],
            'extended' => ['name'],
        ]);

        $encoder = $this->createEncoder();
        $actual = $encoder->encode($criteria, $extendableDefinition, $input, SerializationFixture::API_BASE_URL);

        static::assertArrayHasKey('extensions', $actual);
        static::assertArrayHasKey('toOne', $actual['extensions']);

        $extension = $actual['extensions']['toOne'];
        static::assertSame('toOne', $extension['name']);
        static::assertArrayNotHasKey('id', $extension);
        static::assertArrayNotHasKey('translated', $extension);
        static::assertArrayNotHasKey('extensions', $extension);
    }

    public function testExtensionExcludesRemovePlainJsonExtensionsWrapper(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions();
        $fixture = new TestBasicWithExtension();
        $input = $this->getExtendableInputWithEntityNames($fixture);

        $criteria = new Criteria();
        $criteria->setExcludes([
            'extendable' => ['extensions'],
        ]);

        $encoder = $this->createEncoder();
        $actual = $encoder->encode($criteria, $extendableDefinition, $input, SerializationFixture::API_BASE_URL);

        static::assertArrayNotHasKey('extensions', $actual);
        static::assertSame('1d23c1b015bf43fb97e89008cf42d6fe', $actual['id']);
    }

    public function testEncodeStructWithToManyExtension(): void
    {
        $extendableDefinition = $this->createExtendableDefinitionWithExtensions(includeScalarRuntimeExtension: false);
        $fixture = new TestBasicWithExtension();

        $encoder = $this->createEncoder();
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        unset($actual['apiAlias']);
        static::assertEquals($fixture->getAdminJsonFixtures(), $actual);
    }

    /**
     * @param array{customFields: mixed}|array{translated: array{customFields: mixed}} $input
     * @param array{customFields: mixed}|array{translated: array{customFields: mixed}} $output
     */
    #[DataProvider('customFieldsProvider')]
    public function testCustomFields(array $input, array $output): void
    {
        $encoder = $this->createEncoder();

        $definition = $this->createDefinition(CustomFieldTestDefinition::class);
        $struct = new class extends Entity {
            use EntityCustomFieldsTrait;
        };
        $struct->assign($input);

        $actual = $encoder->encode(new Criteria(), $definition, $struct, SerializationFixture::API_BASE_URL);

        static::assertSame($output, array_intersect_key($output, $actual));
    }

    /**
     * @return \Generator<string, array{0: array{customFields: mixed}, 1: array{customFields: mixed}}|array{0: array{translated: array{customFields: mixed}}, 1: array{translated: array{customFields: mixed}}}>
     */
    public static function customFieldsProvider(): \Generator
    {
        yield 'Custom field null' => [
            [
                'customFields' => null,
            ],
            [
                'customFields' => null,
            ],
        ];

        yield 'Custom field with empty array' => [
            [
                'customFields' => [],
            ],
            [
                'customFields' => new \stdClass(),
            ],
        ];

        yield 'Custom field with values' => [
            [
                'customFields' => ['bla'],
            ],
            [
                'customFields' => ['bla'],
            ],
        ];

        // translated

        yield 'Custom field translated null' => [
            [
                'translated' => [
                    'customFields' => null,
                ],
            ],
            [
                'translated' => [
                    'customFields' => null,
                ],
            ],
        ];

        yield 'Custom field translated with empty array' => [
            [
                'translated' => [
                    'customFields' => [],
                ],
            ],
            [
                'translated' => [
                    'customFields' => new \stdClass(),
                ],
            ],
        ];

        yield 'Custom field translated with values' => [
            [
                'translated' => [
                    'customFields' => ['bla'],
                ],
            ],
            [
                'translated' => [
                    'customFields' => ['bla'],
                ],
            ],
        ];
    }

    public function testExtensionsForeignKeysAreRemoved(): void
    {
        $encoder = $this->createEncoder();

        $definition = $this->createDefinition(CustomFieldTestDefinition::class);

        $struct1 = new class extends Entity {
            use EntityCustomFieldsTrait;
        };
        $struct1->setUniqueIdentifier('test-id');

        // Add extensions properly using the extension system
        $struct1->addExtension('foreignKeys', new ArrayEntity(['key1' => 'some', 'key2' => 'keys']));
        $struct1->addExtension('otherExtension', new ArrayEntity(['value' => 'test']));

        $actual = $encoder->encode(new Criteria(), $definition, $struct1, SerializationFixture::API_BASE_URL);

        // foreignKeys should be removed but extensions should remain
        static::assertArrayHasKey('extensions', $actual);
        static::assertArrayNotHasKey('foreignKeys', $actual['extensions']);
        static::assertArrayHasKey('otherExtension', $actual['extensions']);
    }

    public function testExtensionsRemovedCompletely(): void
    {
        $encoder = $this->createEncoder();

        $definition = $this->createDefinition(CustomFieldTestDefinition::class);

        $struct2 = new class extends Entity {
            use EntityCustomFieldsTrait;
        };
        $struct2->setUniqueIdentifier('test-id-2');
        $struct2->addExtension('foreignKeys', new ArrayEntity(['key1' => 'some', 'key2' => 'keys']));

        $actual = $encoder->encode(new Criteria(), $definition, $struct2, SerializationFixture::API_BASE_URL);

        // extensions should be completely removed
        static::assertArrayNotHasKey('extensions', $actual);
    }

    public function testExtensionsRemovalPartialEntity(): void
    {
        $encoder = $this->createEncoder();

        $definition = $this->createDefinition(CustomFieldTestDefinition::class);

        $struct2 = new PartialEntity();
        $struct2->set('id', 'test-id-2');
        $actual = $encoder->encode(new Criteria(), $definition, $struct2, SerializationFixture::API_BASE_URL);

        static::assertSame(['translated' => [], 'id' => 'test-id-2', 'apiAlias' => 'partial'], $actual);
    }

    public function testArrayEntity(): void
    {
        $encoder = $this->createEncoder();

        $definition = $this->createDefinition(CustomFieldTestDefinition::class);

        $struct2 = new ArrayEntity();
        $struct2->set('id', 'test-id-2');
        $actual = $encoder->encode(new Criteria(), $definition, $struct2, SerializationFixture::API_BASE_URL);

        static::assertSame(['translated' => [], 'id' => 'test-id-2', 'apiAlias' => 'array'], $actual);
    }

    private function createExtendableDefinitionWithExtensions(bool $includeScalarRuntimeExtension = true): ExtendableDefinition
    {
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());

        if ($includeScalarRuntimeExtension) {
            $extendableDefinition->addExtension(new ScalarRuntimeExtension());
        }

        $extendableDefinition->compile($this->definitionRegistry);

        return $extendableDefinition;
    }

    private function getExtendableInputWithEntityNames(TestBasicWithExtension $fixture): Entity
    {
        $input = $fixture->getInput();
        static::assertInstanceOf(Entity::class, $input);

        $input->internalSetEntityData('extendable', new FieldVisibility([]));

        $toOne = $input->getExtension('toOne');
        static::assertInstanceOf(Entity::class, $toOne);
        $toOne->internalSetEntityData('extended', new FieldVisibility([]));

        $toMany = $input->getExtension('toMany');
        static::assertInstanceOf(EntityCollection::class, $toMany);

        foreach ($toMany as $extension) {
            $extension->internalSetEntityData('extended', new FieldVisibility([]));
        }

        return $input;
    }

    private function createEncoder(): JsonEntityEncoder
    {
        return new JsonEntityEncoder(new Serializer([new StructNormalizer()], [new JsonEncoder()]));
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function createDefinition(string $definitionClass): EntityDefinition
    {
        return $this->definitionRegistry->get($definitionClass);
    }
}
