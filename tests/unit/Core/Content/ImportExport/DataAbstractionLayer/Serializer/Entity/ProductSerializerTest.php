<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\AbstractEntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductSerializer::class)]
class ProductSerializerTest extends TestCase
{
    /**
     * @param array<string, mixed> $record
     * @param list<array<string, mixed>> $expectedMedia
     */
    #[DataProvider('mediaStringProvider')]
    public function testDeserializeMediaString(array $record, array $expectedMedia): void
    {
        $result = $this->deserialize($record);

        static::assertSame($expectedMedia, $result['media'] ?? null);
        static::assertArrayNotHasKey('_error', $result);
    }

    /**
     * @return iterable<string, array{record: array<string, mixed>, expectedMedia: list<array<string, mixed>>}>
     */
    public static function mediaStringProvider(): iterable
    {
        $media = static fn (string $url): array => ['media' => ['id' => 'media-id', 'url' => $url]];

        yield 'media absent' => [
            'record' => [],
            'expectedMedia' => [],
        ];

        yield 'media null' => [
            'record' => ['media' => null],
            'expectedMedia' => [],
        ];

        yield 'media empty string' => [
            'record' => ['media' => ''],
            'expectedMedia' => [],
        ];

        yield 'media string zero is a url' => [
            'record' => ['media' => '0'],
            'expectedMedia' => [$media('0')],
        ];

        yield 'media one url' => [
            'record' => ['media' => 'https://example.com/a.png'],
            'expectedMedia' => [$media('https://example.com/a.png')],
        ];

        yield 'media several urls' => [
            'record' => ['media' => 'https://example.com/a.png|https://example.com/b.png'],
            'expectedMedia' => [$media('https://example.com/a.png'), $media('https://example.com/b.png')],
        ];
    }

    /**
     * @param array<string, mixed> $entity
     */
    #[DataProvider('productMediaProvider')]
    public function testSerializeMediaUrls(array $entity, string $expected): void
    {
        $result = $this->serialize($entity);

        static::assertSame($expected, $result['media']);
    }

    /**
     * @return iterable<string, array{entity: array<string, mixed>, expected: string}>
     */
    public static function productMediaProvider(): iterable
    {
        $first = ['media' => ['url' => 'first-url'], 'position' => 1];
        $second = ['media' => ['url' => 'second-url'], 'position' => 2];
        $medias = [$first, $second];

        yield 'cover absent' => [
            'entity' => ['media' => $medias],
            'expected' => 'first-url|second-url',
        ];

        yield 'cover null' => [
            'entity' => ['media' => $medias, 'cover' => null],
            'expected' => 'first-url|second-url',
        ];

        yield 'cover empty array' => [
            'entity' => ['media' => $medias, 'cover' => []],
            'expected' => 'first-url|second-url',
        ];

        yield 'cover empty string' => [
            'entity' => ['media' => $medias, 'cover' => ''],
            'expected' => 'first-url|second-url',
        ];

        yield 'cover array drops its url' => [
            'entity' => ['media' => $medias, 'cover' => ['media' => ['url' => 'first-url']]],
            'expected' => 'second-url',
        ];

        yield 'cover entity drops its url' => [
            'entity' => ['media' => $medias, 'cover' => (new ProductMediaEntity())->assign([
                'media' => (new MediaEntity())->assign(['url' => 'second-url']),
            ])],
            'expected' => 'first-url',
        ];

        yield 'cover set without product medias' => [
            'entity' => ['media' => [], 'cover' => ['media' => ['url' => 'first-url']]],
            'expected' => '',
        ];

        yield 'product media without media key is skipped' => [
            'entity' => ['media' => [['position' => 0], $second]],
            'expected' => 'second-url',
        ];

        yield 'product media with null media is skipped' => [
            'entity' => ['media' => [['media' => null, 'position' => 0], $second]],
            'expected' => 'second-url',
        ];

        yield 'product media with empty media array and a cover is skipped' => [
            'entity' => ['media' => [['media' => [], 'position' => 0], $first], 'cover' => ['media' => ['url' => 'second-url']]],
            'expected' => 'first-url',
        ];

        yield 'product media with empty string media is skipped' => [
            'entity' => ['media' => [['media' => '', 'position' => 0], $second]],
            'expected' => 'second-url',
        ];

        yield 'product media with string zero media is skipped' => [
            'entity' => ['media' => [['media' => '0', 'position' => 0], $second]],
            'expected' => 'second-url',
        ];

        yield 'product media with non-empty string media is skipped' => [
            'entity' => ['media' => [['media' => 'abc', 'position' => 0], $second]],
            'expected' => 'second-url',
        ];

        yield 'product media with integer media is skipped' => [
            'entity' => ['media' => [['media' => 5, 'position' => 0], $second]],
            'expected' => 'second-url',
        ];

        yield 'product media with empty media array is skipped' => [
            'entity' => ['media' => [['media' => [], 'position' => 0], $second]],
            'expected' => 'second-url',
        ];

        yield 'product media entity without media is skipped' => [
            'entity' => ['media' => [(new ProductMediaEntity())->assign(['media' => null, 'position' => 0]), $second]],
            'expected' => 'second-url',
        ];

        yield 'product media entity with media is exported' => [
            'entity' => ['media' => [(new ProductMediaEntity())->assign([
                'media' => (new MediaEntity())->assign(['url' => 'entity-url']),
                'position' => 0,
            ]), $second]],
            'expected' => 'entity-url|second-url',
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<mixed> $expected
     */
    #[DataProvider('configuratorSettingsProvider')]
    public function testDeserializeConfiguratorSettings(array $record, array $expected): void
    {
        $result = $this->deserialize($record);

        static::assertSame($expected, $result['configuratorSettings'] ?? null);
    }

    /**
     * @return iterable<string, array{record: array<string, mixed>, expected: array<mixed>}>
     */
    public static function configuratorSettingsProvider(): iterable
    {
        $option = ['id' => 'option-id'];
        $setting = ['optionId' => 'option-id', 'product' => ['id' => 'parent-id']];

        yield 'parentId and options given' => [
            'record' => ['parentId' => 'parent-id', 'options' => [$option]],
            'expected' => [$setting],
        ];

        yield 'option without id is skipped' => [
            'record' => ['parentId' => 'parent-id', 'options' => [[], $option]],
            'expected' => [$setting],
        ];

        yield 'option with null id is skipped' => [
            'record' => ['parentId' => 'parent-id', 'options' => [['id' => null], $option]],
            'expected' => [$setting],
        ];

        yield 'option with empty string id is skipped' => [
            'record' => ['parentId' => 'parent-id', 'options' => [['id' => ''], $option]],
            'expected' => [$setting],
        ];

        yield 'parentId zero is used' => [
            'record' => ['parentId' => '0', 'options' => [$option]],
            'expected' => [['optionId' => 'option-id', 'product' => ['id' => '0']]],
        ];

        yield 'option id zero is used' => [
            'record' => ['parentId' => 'parent-id', 'options' => [['id' => '0']]],
            'expected' => [['optionId' => '0', 'product' => ['id' => 'parent-id']]],
        ];

        yield 'option id empty array is skipped' => [
            'record' => ['parentId' => 'parent-id', 'options' => [['id' => []], $option]],
            'expected' => [$setting],
        ];

        yield 'option id integer is skipped' => [
            'record' => ['parentId' => 'parent-id', 'options' => [['id' => 5], $option]],
            'expected' => [$setting],
        ];

        yield 'option that is a string is skipped' => [
            'record' => ['parentId' => 'parent-id', 'options' => ['abc', $option]],
            'expected' => [$setting],
        ];

        yield 'only options without id leave no settings' => [
            'record' => ['parentId' => 'parent-id', 'options' => [['id' => '']]],
            'expected' => [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    #[DataProvider('noConfiguratorSettingsProvider')]
    public function testDeserializeWithoutConfiguratorSettings(array $record): void
    {
        $result = $this->deserialize($record);

        static::assertArrayNotHasKey('configuratorSettings', $result);
    }

    /**
     * @return iterable<string, array{record: array<string, mixed>}>
     */
    public static function noConfiguratorSettingsProvider(): iterable
    {
        $option = ['id' => 'option-id'];

        yield 'parentId absent' => [
            'record' => ['options' => [$option]],
        ];

        yield 'parentId null' => [
            'record' => ['parentId' => null, 'options' => [$option]],
        ];

        yield 'parentId empty string' => [
            'record' => ['parentId' => '', 'options' => [$option]],
        ];

        yield 'options absent' => [
            'record' => ['parentId' => 'parent-id'],
        ];

        yield 'options null' => [
            'record' => ['parentId' => 'parent-id', 'options' => null],
        ];

        yield 'options empty array' => [
            'record' => ['parentId' => 'parent-id', 'options' => []],
        ];

        yield 'parentId empty array' => [
            'record' => ['parentId' => [], 'options' => [$option]],
        ];

        yield 'options empty string' => [
            'record' => ['parentId' => 'parent-id', 'options' => ''],
        ];

        yield 'options string zero' => [
            'record' => ['parentId' => 'parent-id', 'options' => '0'],
        ];

        yield 'options non-empty string' => [
            'record' => ['parentId' => 'parent-id', 'options' => 'abc'],
        ];
    }

    #[DataProvider('coverOffContractProvider')]
    public function testSerializeCoverOffContract(mixed $cover, string $expected): void
    {
        $entity = [
            'media' => [['media' => ['url' => 'first-url'], 'position' => 1]],
            'cover' => $cover,
        ];

        $result = $this->serialize($entity);

        static::assertSame($expected, $result['media']);
    }

    /**
     * @return iterable<string, array{cover: mixed, expected: string}>
     */
    public static function coverOffContractProvider(): iterable
    {
        yield 'cover non-empty string' => [
            'cover' => 'abc',
            'expected' => 'first-url',
        ];

        yield 'cover true' => [
            'cover' => true,
            'expected' => 'first-url',
        ];

        yield 'cover non-zero integer' => [
            'cover' => 3,
            'expected' => 'first-url',
        ];

        yield 'cover plain object' => [
            'cover' => new \stdClass(),
            'expected' => 'first-url',
        ];
    }

    #[DataProvider('nonStringMediaProvider')]
    public function testDeserializeNonStringMediaYieldsError(mixed $media): void
    {
        $result = $this->deserialize(['media' => $media]);

        static::assertEquals(ImportExportException::deserializationFailed('media', null, 'string'), $result['_error'] ?? null);
    }

    /**
     * @return iterable<string, array{media: mixed}>
     */
    public static function nonStringMediaProvider(): iterable
    {
        yield 'media empty array' => ['media' => []];
        yield 'media non-empty array' => ['media' => ['x']];
        yield 'media integer zero' => ['media' => 0];
        yield 'media integer' => ['media' => 5];
        yield 'media float zero' => ['media' => 0.0];
        yield 'media float' => ['media' => 1.5];
        yield 'media false' => ['media' => false];
        yield 'media true' => ['media' => true];
        yield 'media object' => ['media' => new \stdClass()];
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, mixed>
     */
    private function serialize(array $entity): array
    {
        return $this->withErrorsAsExceptions(
            fn (): array => iterator_to_array($this->createSerializer()->serialize(new Config([], [], []), $this->getProductDefinition(), $entity))
        );
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function deserialize(array $record): array
    {
        return $this->withErrorsAsExceptions(
            fn (): array => iterator_to_array($this->createSerializer()->deserialize(new Config([], [], []), $this->getProductDefinition(), $record))
        );
    }

    /**
     * @param \Closure(): array<string, mixed> $callback
     *
     * @return array<string, mixed>
     */
    private function withErrorsAsExceptions(\Closure $callback): array
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function createSerializer(): ProductSerializer
    {
        $fieldSerializer = static::createStub(AbstractFieldSerializer::class);
        $fieldSerializer->method('deserialize')->willReturnCallback(
            static fn (Config $config, Field $field, mixed $value): mixed => $value
        );

        $mediaSerializer = static::createStub(AbstractEntitySerializer::class);
        $mediaSerializer->method('deserialize')->willReturnCallback(
            static fn (Config $config, EntityDefinition $definition, mixed $entity): array => ['id' => 'media-id', 'url' => $entity['url']]
        );

        $registry = static::createStub(SerializerRegistry::class);
        $registry->method('getFieldSerializer')->willReturn($fieldSerializer);
        $registry->method('getEntity')->willReturn($mediaSerializer);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('searchIds')->willReturn(new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()));

        $serializer = new ProductSerializer($repository, $repository, $repository, $repository);
        $serializer->setRegistry($registry);

        return $serializer;
    }

    private function getProductDefinition(): EntityDefinition
    {
        return (new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class, ProductMediaDefinition::class, MediaDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        ))->getByEntityName(ProductDefinition::ENTITY_NAME);
    }
}
